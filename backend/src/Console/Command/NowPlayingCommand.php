<?php

namespace App\Console\Command;

use App\Environment;
use AzuraCast\Api\Client;
use AzuraCast\Api\Dto\AdminRelayDto;
use AzuraCast\Api\Dto\AdminRelayUpdateDto;
use GuzzleHttp\Psr7\Uri;
use NowPlaying\AdapterFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:nowplaying',
    description: 'Send "Now Playing" information to the parent AzuraCast server.'
)]
class NowPlayingCommand extends Command
{
    public function __construct(
        protected Client $api,
        protected AdapterFactory $adapterFactory
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $baseUrl = Environment::getParentBaseUrl();
        $apiKey = Environment::getParentApiKey();

        if (empty($baseUrl) || empty($apiKey)) {
            $io->error(
                'Base URL or API key is not specified. Please supply these values in "azurarelay.env" to continue!.'
            );
            return 1;
        }

        $configDir = Environment::getParentDirectory() . '/stations';
        $relayInfoPath = $configDir . '/stations.json';

        if (!is_file($relayInfoPath)) {
            $io->error('Relay information file doesn\'t exist! Skipping.');
            return 1;
        }

        $relaysRaw = json_decode(file_get_contents($relayInfoPath), true, 512, JSON_THROW_ON_ERROR);

        $np = [];
        foreach ($relaysRaw as $relayRaw) {
            $relay = AdminRelayDto::fromArray($relayRaw);

            $localUri = (new Uri('http://localhost'))
                ->withPort($relay->getPort())
                ->withUserInfo('admin:' . $relay->getAdminPassword());

            $npAdapter = $this->adapterFactory->getIcecastAdapter($localUri);

            foreach ($relay->getMounts() as $mount) {
                $np_mount = $npAdapter->getNowPlaying($mount->getPath(), true);
                $np[$relay->getId()][$mount->getPath()] = $np_mount;
            }
        }

        if (!empty($np)) {
            $this->api->admin()->relays()->update(
                new AdminRelayUpdateDto(
                    Environment::getRelayBaseUrl(),
                    Environment::getRelayName(),
                    Environment::relayIsPublic(),
                    $np
                )
            );
        }

        $io->success('Now Playing updated!');
        return 0;
    }
}
