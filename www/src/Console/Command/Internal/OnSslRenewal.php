<?php

declare(strict_types=1);

namespace App\Console\Command\Internal;

use AzuraCast\Api\Client;
use Psr\Log\LoggerInterface;
use Supervisor\Supervisor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:internal:on-ssl-renewal',
    description: 'Reload relays when an SSL certificate changes.',
)]
class OnSslRenewal extends Command
{
    public function __construct(
        protected LoggerInterface $logger,
        protected Client $api,
        protected Supervisor $supervisor
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $relays = $this->api->admin()->relays()->list();

        foreach($relays as $relay) {
            $programName = 'station_'.$relay->getId().'_relay';

            $this->supervisor->signalProcess($programName, 'HUP');
            $this->logger->info(
                'Relay "' . $relay->getName() . '" reloaded.',
                ['relay_id' => $relay->getId()]
            );
        }

        return 0;
    }
}
