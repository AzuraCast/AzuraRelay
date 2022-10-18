<?php

namespace App\AzuraRelay;

use App\Environment;
use AzuraCast\Api\Dto\AdminRelayDto;
use Psr\Log\LoggerInterface;
use Supervisor\Supervisor as SupervisorClient;
use Symfony\Component\Filesystem\Filesystem;

final class Supervisor
{
    public function __construct(
        private SupervisorClient $supervisor,
        private LoggerInterface $logger,
        private Environment $environment
    ) {
    }

    /**
     * @param AdminRelayDto[] $stations
     */
    public function writeForStations(
        array $stations
    ): void {
        $supervisorPath = $this->environment->getStationsDirectory() . '/supervisord.conf';
        $config = [];

        foreach ($stations as $relay) {
            $config[] = $this->getConfigForStation($relay);
        }

        (new Filesystem())->dumpFile($supervisorPath, implode("\n", $config));

        $this->reload();
    }

    private function getConfigForStation(
        AdminRelayDto $relay
    ): string {
        $groupName = 'station_' . $relay->getId();
        $programName = 'station_' . $relay->getId() . '_relay';

        $icecastXml = Icecast::getConfigPathForStation($this->environment, $relay);
        $configDir = dirname($icecastXml);

        return <<<INI
        [group:{$groupName}]
        programs={$programName}

        [program:{$programName}]
        user=app
        command=/usr/local/bin/icecast -c {$icecastXml}
        directory={$configDir}
        stdout_logfile=/dev/stdout
        stdout_logfile_maxbytes=0
        stderr_logfile=/dev/stderr
        stderr_logfile_maxbytes=0
        autorestart=true

        INI;
    }

    /**
     * Trigger a supervisord reload and restart all relevant services.
     *
     * @return array A list of affected service groups (either stopped, removed or changed).
     */
    public function reload(): array
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
}
