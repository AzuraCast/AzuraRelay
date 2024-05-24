<?php

namespace App\AzuraRelay;

use App\Environment;
use App\Service\Acme;
use App\Xml\Writer;
use AzuraCast\Api\Dto\AdminRelayDto;
use GuzzleHttp\Psr7\Uri;
use Symfony\Component\Filesystem\Filesystem;

final class Icecast
{
    /**
     * @param AdminRelayDto[] $stations
     */
    public function writeForStations(
        array $stations
    ): void {
        foreach ($stations as $station) {
            $this->writeForStation($station);
        }
    }

    public function writeForStation(
        AdminRelayDto $relay
    ): void {
        $baseUrl = Environment::getParentBaseUrl();
        $relayBaseUrl = Environment::getRelayBaseUrl();

        $configPath = self::getConfigPathForStation($relay);
        $baseDir = dirname($configPath);

        $relayUri = new Uri($relayBaseUrl);

        [$certPath, $certKey] = Acme::getCertificatePaths();

        $config = [
            'location' => 'AzuraCast',
            'admin' => 'icemaster@localhost',
            'hostname' => $relayUri->getHost(),
            'limits' => [
                'clients' => 15000,
                'sources' => count($relay->getMounts()),
                'queue-size' => 524288,
                'client-timeout' => 30,
                'header-timeout' => 15,
                'source-timeout' => 10,
                'burst-size' => 65535,
            ],
            'authentication' => [
                'source-password' => $this->generatePassword(),
                'relay-password' => $relay->getRelayPassword(),
                'admin-user' => 'admin',
                'admin-password' => $relay->getAdminPassword(),
            ],

            'listen-socket' => [
                'port' => $relay->getPort(),
            ],

            'mount' => [],
            'fileserve' => 1,
            'paths' => [
                'basedir' => '/usr/local/share/icecast',
                'logdir' => $baseDir,
                'webroot' => '/usr/local/share/icecast/web',
                'adminroot' => '/usr/local/share/icecast/admin',
                'pidfile' => $baseDir . '/' . $relay->getShortcode() . '.pid',
                'alias' => [
                    '@source' => '/',
                    '@dest' => '/status.xsl',
                ],
                'ssl-private-key' => $certKey,
                'ssl-certificate' => $certPath,
                // phpcs:disable Generic.Files.LineLength
                'ssl-allowed-ciphers' => 'ECDH+AESGCM:DH+AESGCM:ECDH+AES256:DH+AES256:ECDH+AES128:DH+AES:RSA+AESGCM:RSA+AES:!aNULL:!MD5:!DSS',
                // phpcs:enable
                'x-forwarded-for' => '127.0.0.1',
            ],
            'logging' => [
                'accesslog' => $relay->getShortcode() . 'access.log',
                'errorlog' => '/dev/stderr',
                'loglevel' => 2,
                'logsize' => 10000,
            ],
            'security' => [
                'chroot' => 0,
            ],
        ];

        $uri = new Uri($baseUrl);

        if ('icecast' === $relay->getType() && !empty($relay->getRelayPassword())) {
            // Use the built-in Icecast relay mechanism.
            $config['master-server'] = $uri->getHost();
            $config['master-server-port'] = $relay->getPort();
            $config['master-update-interval'] = 120;
            $config['master-password'] = $relay->getRelayPassword();
        } else {
            // Manually relay each individual mountpoint.
            $config['mount'] = [];
            $config['relay'] = [];

            foreach ($relay->getMounts() as $row) {
                $config['mount'][] = [
                    '@type' => 'normal',
                    'mount-name' => $row->getPath(),
                    'charset' => 'UTF8',

                    'stream-name' => $relay->getName(),
                    'stream-description' => $relay->getDescription(),
                    'stream-url' => $relay->getUrl(),
                    'genre' => $relay->getGenre(),
                ];

                $config['relay'][] = [
                    'server' => $uri->getHost(),
                    'port' => $relay->getPort(),
                    'mount' => $row->getPath(),
                    'local-mount' => $row->getPath(),
                ];
            }
        }

        $icecast_config_str = Writer::toString($config, 'icecast');

        // Strip the first line (the XML charset)
        $icecast_config_str = substr($icecast_config_str, strpos($icecast_config_str, "\n") + 1);

        (new Filesystem())->dumpFile($configPath, $icecast_config_str);
    }

    /**
     * Generate a randomized password of specified length.
     *
     * @param int $char_length
     *
     * @return string
     */
    private function generatePassword(int $char_length = 8): string
    {
        // String of all possible characters. Avoids using certain letters and numbers that closely resemble others.
        $numeric_chars = str_split('234679');
        $uppercase_chars = str_split('ACDEFGHJKLMNPQRTWXYZ');
        $lowercase_chars = str_split('acdefghjkmnpqrtwxyz');

        $chars = [$numeric_chars, $uppercase_chars, $lowercase_chars];

        $password = '';
        for ($i = 1; $i <= $char_length; $i++) {
            $char_array = $chars[$i % 3];
            $password .= $char_array[mt_rand(0, count($char_array) - 1)];
        }

        return str_shuffle($password);
    }

    public static function getConfigPathForStation(AdminRelayDto $relay): string
    {
        return Environment::getStationsDirectory() . '/' . $relay->getShortcode() . '.xml';
    }
}
