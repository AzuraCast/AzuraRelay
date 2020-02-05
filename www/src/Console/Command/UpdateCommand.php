<?php
namespace App\Console\Command;

use App\Console\Command\CommandAbstract;
use App\Settings;
use AzuraCast\Api\Client;
use AzuraCast\Api\Dto\AdminRelayDto;
use GuzzleHttp\Psr7\Uri;
use Monolog\Logger;
use Supervisor\Supervisor;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpdateCommand extends CommandAbstract
{
    public function __invoke(
        SymfonyStyle $io,
        Settings $settings,
        Client $api,
        Supervisor $supervisor
    ) {
        $io->title('AzuraRelay Updater');

        $baseUrl = getenv('AZURACAST_BASE_URL');
        $apiKey = getenv('AZURACAST_API_KEY');

        if (empty($baseUrl) || empty($apiKey)) {
            $io->error('Base URL or API key is not specified. Please supply these values in "azurarelay.env" to continue!.');
            return 1;
        }

        $configDir = dirname($settings[Settings::BASE_DIR]).'/stations';
        $supervisorPath = $configDir.'/supervisord.conf';

        $relays = $api->admin()->relays()->list();

        // Write relay information to JSON file.
        $relayInfoPath = $configDir.'/stations.json';
        file_put_contents($relayInfoPath, json_encode($relays));

        // Write supervisord config
        $supervisorConfig = [];
        $stations = [];

        foreach($relays as $relay) {
            $stations[] = $relay->getName();

            $icecastXml = $this->writeStationConfiguration($relay, $configDir, $baseUrl);

            $groupName = 'station_'.$relay->getId();
            $programName = 'station_'.$relay->getId().'_relay';

            $supervisorConfig[] = '[group:' . $groupName . ']';
            $supervisorConfig[] = 'programs=' . $programName;
            $supervisorConfig[] = '';

            $supervisorConfig[] = '[program:' . $programName . ']';
            $supervisorConfig[] = 'user=azurarelay';
            $supervisorConfig[] = 'command=/usr/local/bin/icecast -c '.$icecastXml;
            $supervisorConfig[] = 'directory='.$configDir;
            $supervisorConfig[] = 'stdout_logfile='.$configDir.'/'.$relay->getShortcode().'.log';
            $supervisorConfig[] = 'stdout_logfile_maxbytes=5MB';
            $supervisorConfig[] = 'stdout_logfile_backups=10';
            $supervisorConfig[] = 'redirect_stderr=true';
            $supervisorConfig[] = '';
        }

        $io->listing($stations);

        file_put_contents($supervisorPath, implode("\n", $supervisorConfig));

        $this->reloadSupervisor($supervisor);

        $io->success('Update successful. Relay is functioning!');
        return 0;
    }

    protected function writeStationConfiguration(
        AdminRelayDto $relay,
        string $baseDir,
        string $baseUrl
    ): string {
        $configPath = $baseDir.'/'.$relay->getShortcode().'.xml';

        $config = [
            'location' => 'AzuraCast',
            'admin' => 'icemaster@localhost',
            'hostname' => 'localhost',
            'limits' => [
                'clients' => 2500,
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
                'pidfile' => $baseDir . '/'.$relay->getShortcode().'.pid',
                'alias' => [
                    '@source' => '/',
                    '@dest' => '/status.xsl',
                ],
                'ssl-private-key' => '/etc/letsencrypt/ssl.key',
                'ssl-certificate' => '/etc/letsencrypt/ssl.crt',
                'ssl-allowed-ciphers' => 'ECDH+AESGCM:DH+AESGCM:ECDH+AES256:DH+AES256:ECDH+AES128:DH+AES:RSA+AESGCM:RSA+AES:!aNULL:!MD5:!DSS',
                'x-forwarded-for' => '127.0.0.1',
                'all-x-forwarded-for' => '1',
            ],
            'logging' => [
                'accesslog' => $relay->getShortcode().'access.log',
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

        $writer = new \App\Xml\Writer;
        $icecast_config_str = $writer->toString($config, 'icecast');

        // Strip the first line (the XML charset)
        $icecast_config_str = substr($icecast_config_str, strpos($icecast_config_str, "\n") + 1);

        file_put_contents($configPath, $icecast_config_str);

        return $configPath;
    }

    /**
     * Trigger a supervisord reload and restart all relevant services.
     *
     * @return array A list of affected service groups (either stopped, removed or changed).
     */
    protected function reloadSupervisor(Supervisor $supervisor): array
    {
        $logger = \App\Logger::getInstance();

        $reload_result = $supervisor->reloadConfig();

        $affected_groups = [];

        [$reload_added, $reload_changed, $reload_removed] = $reload_result[0];

        if (!empty($reload_removed)) {
            $logger->debug('Removing supervisor groups.', $reload_removed);

            foreach ($reload_removed as $group) {
                $affected_groups[] = $group;
                $supervisor->stopProcessGroup($group);
                $supervisor->removeProcessGroup($group);
            }
        }

        if (!empty($reload_changed)) {
            $logger->debug('Reloading modified supervisor groups.', $reload_changed);

            foreach ($reload_changed as $group) {
                $affected_groups[] = $group;
                $supervisor->stopProcessGroup($group);
                $supervisor->removeProcessGroup($group);
                $supervisor->addProcessGroup($group);
            }
        }

        if (!empty($reload_added)) {
            $logger->debug('Adding new supervisor groups.', $reload_added);

            foreach ($reload_added as $group) {
                $affected_groups[] = $group;
                $supervisor->addProcessGroup($group);
            }
        }

        return $affected_groups;
    }

    /**
     * Generate a randomized password of specified length.
     *
     * @param int $char_length
     * @return string
     */
    protected function generatePassword($char_length = 8): string
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
}
