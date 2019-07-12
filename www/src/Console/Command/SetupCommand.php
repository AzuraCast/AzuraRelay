<?php
namespace App\Console\Command;

use Azura\Console\Command\CommandAbstract;
use Azura\Settings;
use AzuraCast\Api\Client;
use GuzzleHttp\Psr7\Uri;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SetupCommand extends CommandAbstract
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('app:setup')
            ->setDescription('Run initial setup process.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var \GuzzleHttp\Client $httpClient */
        $httpClient = $this->get(\GuzzleHttp\Client::class);

        $io = new SymfonyStyle($input, $output);
        $io->title('AzuraRelay Setup');
        $io->writeln('Welcome to AzuraRelay! Provide the following items to finish setup.');

        $io->section('AzuraCast Installation URL');
        $io->writeln('AzuraRelay has to connect to a "parent" AzuraCast instance to relay its broadcast(s).');
        $io->writeln('Provide the base URL of that installation (including "http://" or "https://") to continue.');

        $question = new Question\Question('AzuraCast Installation URL', getenv('AZURACAST_BASE_URL'));
        $question->setMaxAttempts(10);
        $question->setValidator(function($value) use ($httpClient) {
            try {
                $api = \AzuraCast\Api\Client::create($value, null, $httpClient);
                $np = $api->nowPlaying();
            } catch(\Exception $e) {
                throw new \RuntimeException(sprintf('Could not connect to AzuraCast instance at %s: %s', $value, $e->getMessage()));
            }

            return $value;
        });

        $baseUrl = $io->askQuestion($question);

        if (empty($baseUrl)) {
            $io->error('You must provide an AzuraCast Base URL to continue.');
            return 1;
        }

        $apiKeyUrl = (string)(new Uri($baseUrl))->withPath('/api_keys');

        $io->section('AzuraCast API Key');
        $io->writeln('You must provide an API key for an authorized account on this installation.');
        $io->writeln('You can generate a new API key at:');
        $io->listing([$apiKeyUrl]);

        $question = new Question\Question('AzuraCast API Key', getenv('AZURACAST_API_KEY'));
        $question->setMaxAttempts(10);
        $question->setValidator(function($value) use ($baseUrl, $httpClient) {
            $api = Client::create($baseUrl, $value, $httpClient);
            $relays = $api->admin()->relays()->list();

            if (0 === count($relays)) {
                throw new \RuntimeException('No relayable streams were found on the remote server. Make sure your account has permission to "Manage Broadcasting" for the station you want to relay.');
            }

            return $value;
        });

        $apiKey = $io->askQuestion($question);

        if (empty($apiKey)) {
            $io->error('You must provide an API key to continue.');
            return 1;
        }

        $api = Client::create($baseUrl, $apiKey, $httpClient);
        $relays = $api->admin()->relays()->list();

        $question = new Question\ConfirmationQuestion(sprintf('This installation has %d relayable streams. Relay all streams?', count($relays)), true);
        $useAllStreams = $io->askQuestion($question);

        if ($useAllStreams) {
            $streams = '';
        } else {
            $stations = [];
            foreach($relays as $relay) {
                $stations[$relay->getId()] = $relay->getName();
            }

            $question = new Question\ChoiceQuestion('Select the station(s) to relay', $stations);
            $question->setMultiselect(true);

            $streamsRaw = $io->askQuestion($question);
            $streams = implode(',', $streamsRaw);
        }

        $io->section('Generating configuration file...');

        $envFileParts = [
            'AZURACAST_BASE_URL' => $baseUrl,
            'AZURACAST_API_KEY' => $apiKey,
            'AZURACAST_STATIONS' => $streams,
        ];

        $envFile = [];
        foreach($envFileParts as $envFileKey => $envFileValue) {
            $envFile[] = $envFileKey.'='.$envFileValue;
        }
        $envFile = implode("\n", $envFile);

        /** @var Settings $settings */
        $settings = $this->get(Settings::class);

        $temp_path = $settings[Settings::TEMP_DIR].'/azurarelay.env';
        file_put_contents($temp_path, $envFile);

        $io->note($envFile);

        $io->success('Setup is complete.');
        return 0;
    }
}
