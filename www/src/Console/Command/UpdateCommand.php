<?php
namespace App\Console\Command;

use App\Console\Command\CommandAbstract;
use App\Environment;
use App\Xml\Writer;
use AzuraCast\Api\Client;
use AzuraCast\Api\Dto\AdminRelayDto;
use GuzzleHttp\Psr7\Uri;
use Psr\Log\LoggerInterface;
use Supervisor\Supervisor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:update',
    description: 'Update local relay configuration based on remote setup.'
)]
class UpdateCommand extends Command
{
    public function __construct(
        protected LoggerInterface $logger,
        protected Environment $environment,
        protected Client $api,
        protected Supervisor $supervisor
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('AzuraRelay Updater');

        $baseUrl = $this->environment->getParentBaseUrl();
        $apiKey = $this->environment->getParentApiKey();

        if (empty($baseUrl) || empty($apiKey)) {
            $io->error(
                'Base URL or API key is not specified. Please supply these values in "azurarelay.env" to continue!.'
            );
            return 1;
        }

        $configDir = $this->environment->getParentDirectory() . '/stations';
        $supervisorPath = $configDir . '/supervisord.conf';

        $relays = $this->api->admin()->relays()->list();

        // Write relay information to JSON file.
        $relayInfoPath = $configDir . '/stations.json';
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

        $this->reloadSupervisor();

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
                'pidfile' => $baseDir . '/'.$relay->getShortcode().'.pid',
                'alias' => [
                    '@source' => '/',
                    '@dest' => '/status.xsl',
                ],
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

        $icecast_config_str = (new Writer)->toString($config, 'icecast');

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
    protected function reloadSupervisor(): array
    {
        $reload_result = $this->supervisor->reloadConfig();

        $affected_groups = [];

        [$reload_added, $reload_changed, $reload_removed] = $reload_result[0];

        if (!empty($reload_removed)) {
            $this->logger->debug('Removing supervisor groups.', $reload_removed);

            foreach ($reload_removed as $group) {
                $affected_groups[] = $group;
                $this->supervisor->stopProcessGroup($group);
                $this->supervisor->removeProcessGroup($group);
            }
        }

        if (!empty($reload_changed)) {
            $this->logger->debug('Reloading modified supervisor groups.', $reload_changed);

            foreach ($reload_changed as $group) {
                $affected_groups[] = $group;
                $this->supervisor->stopProcessGroup($group);
                $this->supervisor->removeProcessGroup($group);
                $this->supervisor->addProcessGroup($group);
            }
        }

        if (!empty($reload_added)) {
            $this->logger->debug('Adding new supervisor groups.', $reload_added);

            foreach ($reload_added as $group) {
                $affected_groups[] = $group;
                $this->supervisor->addProcessGroup($group);
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
