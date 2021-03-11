<?php
namespace App\Console\Command;

use App\Console\Command\CommandAbstract;
use App\Environment;
use AzuraCast\Api\Client;
use AzuraCast\Api\Dto\AdminRelayDto;
use AzuraCast\Api\Dto\AdminRelayUpdateDto;
use GuzzleHttp\Psr7\Uri;
use NowPlaying\Adapter\AdapterFactory;
use NowPlaying\Adapter\Icecast;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class NowPlayingCommand extends CommandAbstract
{
    public function __invoke(
        SymfonyStyle $io,
        Client $api,
        AdapterFactory $adapterFactory,
        Environment $environment
    ) {
        $io->title('AzuraRelay Now Playing');

        $baseUrl = $environment->getParentBaseUrl();
        $apiKey = $environment->getParentApiKey();

        if (empty($baseUrl) || empty($apiKey)) {
            $io->error('Base URL or API key is not specified. Please supply these values in "azurarelay.env" to continue!.');
            return 1;
        }

        $configDir = $environment->getParentDirectory().'/stations';
        $relayInfoPath = $configDir.'/stations.json';

        if (!file_exists($relayInfoPath)) {
            $io->error('Relay information file doesn\'t exist! Skipping.');
            return 1;
        }

        $relaysRaw = json_decode(file_get_contents($relayInfoPath), true, 512, JSON_THROW_ON_ERROR);

        $np = [];
        foreach($relaysRaw as $relayRaw) {
            $relay = AdminRelayDto::fromArray($relayRaw);

            $localUri = (new Uri('http://localhost'))
                ->withPort($relay->getPort())
                ->withUserInfo('admin:'.$relay->getAdminPassword());

            $npAdapter = $adapterFactory->getAdapter(AdapterFactory::ADAPTER_ICECAST, $localUri);

            foreach($relay->getMounts() as $mount) {
                $np_mount = $npAdapter->getNowPlaying($mount->getPath(), true);
                $np[$relay->getId()][$mount->getPath()] = $np_mount;
            }
        }

        if (!empty($np)) {
            $api->admin()->relays()->update(new AdminRelayUpdateDto(
                $environment->getRelayBaseUrl(),
                $environment->getRelayName(),
                $environment->relayIsPublic(),
                $np
            ));
        }

        $io->success('Now Playing updated!');
        return 0;
    }
}
