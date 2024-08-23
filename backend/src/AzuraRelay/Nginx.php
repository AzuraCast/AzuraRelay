<?php

namespace App\AzuraRelay;

use App\Environment;
use AzuraCast\Api\Dto\AdminRelayDto;
use Supervisor\Supervisor as SupervisorClient;
use Symfony\Component\Filesystem\Filesystem;

final class Nginx
{
    private const string PROCESS_NAME = 'nginx';

    public function __construct(
        private SupervisorClient $supervisor
    ) {
    }

    /**
     * @param AdminRelayDto[] $stations
     */
    public function writeForStations(
        array $stations
    ): void {
        $config = [];
        foreach ($stations as $station) {
            $config[] = $this->getConfigForStation($station);
        }

        $configPath = Environment::getStationsDirectory() . '/nginx.conf';
        (new Filesystem())->dumpFile($configPath, implode("\n", $config));

        $this->reload();
    }

    private function getConfigForStation(
        AdminRelayDto $relay
    ): string {
        $listenBaseUrl = preg_quote('/listen/' . $relay->getShortcode(), null);
        $port = $relay->getPort();

        return <<<NGINX
        location ~ ^({$listenBaseUrl}|/radio/{$port})\$ {
            return 302 \$uri/;
        }

        location ~ ^({$listenBaseUrl}|/radio/{$port})/(.*)\$ {
            include proxy_params;

            proxy_intercept_errors    on;
            proxy_next_upstream       error timeout invalid_header;
            proxy_redirect            off;
            proxy_connect_timeout     60;

            proxy_set_header Host localhost:{$port};

            set \$args \$args&_ic2=1;
            proxy_pass http://127.0.0.1:{$port}/\$2?\$args;
        }
        NGINX;
    }

    public function reload(): void
    {
        $this->supervisor->signalProcess(self::PROCESS_NAME, 'HUP');
    }
}
