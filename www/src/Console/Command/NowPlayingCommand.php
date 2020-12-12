<?php
namespace App\Console\Command;

use App\Console\Command\CommandAbstract;
use App\Environment;
use AzuraCast\Api\Client;
use AzuraCast\Api\Dto\AdminRelayDto;
use AzuraCast\Api\Dto\AdminRelayUpdateDto;
use GuzzleHttp\Psr7\Uri;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class NowPlayingCommand extends CommandAbstract
{
    public function __invoke(
        SymfonyStyle $io,
        Client $api,
        Environment $environment
    ) {
        $io->title('AzuraRelay Now Playing');

        $baseUrl = getenv('AZURACAST_BASE_URL');
        $apiKey = getenv('AZURACAST_API_KEY');

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

        $relaysRaw = json_decode(file_get_contents($relayInfoPath), true);

        $np = [];
        foreach($relaysRaw as $relayRaw) {
            $relay = AdminRelayDto::fromArray($relayRaw);

            $localUri = (new Uri('http://localhost'))
                ->withPort($relay->getPort());

            $npAdapter = new \NowPlaying\Adapter\Icecast($localUri);
            $npAdapter->setAdminPassword($relay->getAdminPassword());

            foreach($relay->getMounts() as $mount) {
                try {
                    $np_mount = $npAdapter->getNowPlaying($mount->getPath());
                    $np_mount['listeners']['clients'] = $npAdapter->getClients($mount->getPath(), true);

                    $np[$relay->getId()][$mount->getPath()] = $np_mount;
                } catch(\NowPlaying\Exception $e) {
                    $io->error(sprintf('NowPlaying adapter error: %s', $e->getMessage()));
                }
            }
        }

        if (!empty($np)) {
            $api->admin()->relays()->update(new AdminRelayUpdateDto(
                getenv('AZURARELAY_BASE_URL'),
                getenv('AZURARELAY_NAME'),
                (bool)getenv('AZURARELAY_IS_PUBLIC'),
                $np
            ));
        }

        $io->success('Now Playing updated!');
        return 0;
    }
}
