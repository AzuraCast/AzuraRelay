<?php
namespace App\Console\Command;

use App\AzuraRelay\Icecast;
use App\AzuraRelay\Nginx;
use App\AzuraRelay\Supervisor;
use App\Environment;
use AzuraCast\Api\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:update',
    description: 'Update local relay configuration based on remote setup.'
)]
final class UpdateCommand extends Command
{
    public function __construct(
        private LoggerInterface $logger,
        private Environment $environment,
        private Client $api,
        private Supervisor $supervisor,
        private Icecast $icecast,
        private Nginx $nginx,
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

        $relayBaseUrl = $this->environment->getRelayBaseUrl();

        if (empty($relayBaseUrl)) {
            $io->error(
                'Relay Base URL is not specified. Please supply it in "azurarelay.env" to continue!.'
            );
            return 1;
        }

        $relays = $this->api->admin()->relays()->list();

        // Write relay information to JSON file.
        $relayInfoPath = $this->environment->getConfigDirectory() . '/stations.json';
        file_put_contents($relayInfoPath, json_encode($relays, JSON_THROW_ON_ERROR));

        // Write and reload configs
        $this->nginx->writeForStations($relays);
        $this->icecast->writeForStations($relays);
        $this->supervisor->writeForStations($relays);

        $io->success('Update successful. Relay is functioning!');
        return 0;
    }
}
