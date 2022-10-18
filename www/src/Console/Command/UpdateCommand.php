<?php
namespace App\Console\Command;

use App\AzuraRelay\Icecast;
use App\AzuraRelay\Nginx;
use App\AzuraRelay\Supervisor;
use App\Environment;
use App\Service\Acme;
use AzuraCast\Api\Client;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:update',
    description: 'Update local relay configuration based on remote setup.'
)]
final class UpdateCommand extends Command
{
    public function __construct(
        private readonly Environment $environment,
        private readonly Client $api,
        private readonly Supervisor $supervisor,
        private readonly Icecast $icecast,
        private readonly Nginx $nginx,
        private readonly Acme $acme
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'restart-all',
            null,
            InputOption::VALUE_NONE,
            'Force a restart of all services.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

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
        $relayInfoPath = $this->environment->getStationsDirectory() . '/stations.json';
        file_put_contents($relayInfoPath, json_encode($relays, JSON_THROW_ON_ERROR));

        // Write and reload configs
        try {
            $this->acme->getCertificate();
        } catch (Exception $e) {
            $io->error($e->getMessage());
        }

        $this->nginx->writeForStations($relays);
        $this->icecast->writeForStations($relays);
        $this->supervisor->writeForStations($relays);

        if ((bool)$input->getOption('restart-all')) {
            $this->supervisor->restartAll();
        }

        $io->success('Update successful. Relay is functioning!');
        return 0;
    }
}
