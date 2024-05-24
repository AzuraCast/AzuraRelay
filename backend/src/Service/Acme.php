<?php

declare(strict_types=1);

namespace App\Service;

use App\AzuraRelay\Nginx;
use App\Environment;
use Exception;
use Monolog\Logger;
use RuntimeException;
use skoerfgen\ACMECert\ACMECert;
use Symfony\Component\Filesystem\Filesystem;

final class Acme
{
    public const string LETSENCRYPT_PROD = 'https://acme-v02.api.letsencrypt.org/directory';
    public const string LETSENCRYPT_DEV = 'https://acme-staging-v02.api.letsencrypt.org/directory';
    public const int THRESHOLD_DAYS = 30;

    public function __construct(
        private readonly Logger $logger,
        private readonly Nginx $nginx
    ) {
    }

    public function getCertificate(bool $force = false): void
    {
        // Check folder permissions.
        $acmeDir = self::getAcmeDirectory();
        $fs = new Filesystem();

        // Build ACME Cert class.
        $directoryUrl = Environment::isProduction() ? self::LETSENCRYPT_PROD : self::LETSENCRYPT_DEV;

        $this->logger->debug(
            sprintf('ACME: Using directory URL: %s', $directoryUrl)
        );

        $acme = new ACMECert($directoryUrl);

        // Build LetsEncrypt settings.
        $acmeEmail = getenv('LETSENCRYPT_EMAIL');
        $acmeDomain = getenv('LETSENCRYPT_HOST');

        if (empty($acmeDomain)) {
            throw new RuntimeException('Skipping LetsEncrypt; no domain(s) set.');
        }

        // Account certificate registration.
        if (file_exists($acmeDir . '/account_key.pem')) {
            $acme->loadAccountKey('file://' . $acmeDir . '/account_key.pem');
        } else {
            $accountKey = $acme->generateECKey();
            $acme->loadAccountKey($accountKey);

            if (!empty($acmeEmail)) {
                $acme->register(true, $acmeEmail);
            } else {
                $acme->register(true);
            }
            $fs->dumpFile($acmeDir . '/account_key.pem', $accountKey);
        }

        $domains = array_map(
            'trim',
            explode(',', $acmeDomain)
        );

        // Renewal check.
        if (
            !$force
            && file_exists($acmeDir . '/acme.crt')
            && empty(array_diff($domains, $acme->getSAN('file://' . $acmeDir . '/acme.crt')))
            && $acme->getRemainingDays('file://' . $acmeDir . '/acme.crt') > self::THRESHOLD_DAYS
        ) {
            throw new RuntimeException('Certificate does not need renewal.');
        }

        $fs->mkdir($acmeDir . '/challenges');

        $domainConfig = [];
        foreach ($domains as $domain) {
            $domainConfig[$domain] = ['challenge' => 'http-01'];
        }

        $handler = function ($opts) use ($acmeDir, $fs) {
            $fs->dumpFile(
                $acmeDir . '/challenges/' . basename($opts['key']),
                $opts['value']
            );

            return function ($opts) use ($acmeDir, $fs) {
                $fs->remove($acmeDir . '/challenges/' . $opts['key']);
            };
        };

        if (!file_exists($acmeDir . '/acme.key')) {
            $acmeKey = $acme->generateECKey();
            $fs->dumpFile($acmeDir . '/acme.key', $acmeKey);
        }

        $fullchain = $acme->getCertificateChain(
            'file://' . $acmeDir . '/acme.key',
            $domainConfig,
            $handler
        );
        $fs->dumpFile($acmeDir . '/acme.crt', $fullchain);

        // Symlink to the shared SSL cert.
        $fs->remove([
            $acmeDir . '/ssl.crt',
            $acmeDir . '/ssl.key',
        ]);

        $fs->symlink($acmeDir . '/acme.crt', $acmeDir . '/ssl.crt');
        $fs->symlink($acmeDir . '/acme.key', $acmeDir . '/ssl.key');

        $this->reloadServices();

        $this->logger->notice('ACME certificate process successful.');
    }

    private function reloadServices(): void
    {
        try {
            $this->nginx->reload();
        } catch (Exception $e) {
            $this->logger->error(
                sprintf('ACME: Could not reload all adapters: %s', $e->getMessage()),
                [
                    'exception' => $e,
                ]
            );
        }
    }

    public static function getAcmeDirectory(): string
    {
        return Environment::getParentDirectory() . '/acme';
    }

    public static function getCertificatePaths(): array
    {
        $acmeDir = self::getAcmeDirectory();
        return [
            $acmeDir . '/ssl.crt',
            $acmeDir . '/ssl.key',
        ];
    }
}
