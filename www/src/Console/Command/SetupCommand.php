<?php
namespace App\Console\Command;

use App\Environment;
use App\Service\GuzzleFactory;
use AzuraCast\Api\Client;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\RequestOptions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:setup',
    description: 'Run initial setup process.'
)]
class SetupCommand extends Command
{
    protected GuzzleFactory $guzzleFactory;

    public function __construct(
        GuzzleFactory $guzzleFactory,
        protected Client $api,
        protected Environment $environment
    ) {
        parent::__construct();

        $this->guzzleFactory = $guzzleFactory->withAddedConfig(
            [
                RequestOptions::TIMEOUT => 15.0,
            ]
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('AzuraRelay Setup');
        $io->writeln('Welcome to AzuraRelay! Provide the following items to finish setup.');

        //
        // Parent installation URL
        //
        $io->section('AzuraCast Installation URL');
        $io->writeln('AzuraRelay has to connect to a "parent" AzuraCast instance to relay its broadcast(s).');
        $io->writeln('Provide the base URL of that installation (including "http://" or "https://") to continue.');

        $question = new Question\Question('AzuraCast Installation URL', getenv('AZURACAST_BASE_URL'));
        $question->setMaxAttempts(10);
        $question->setValidator(function ($value) {
            try {
                $api = Client::create(
                    $value,
                    null,
                    $this->guzzleFactory->getDefaultConfig()
                );
                $np = $api->nowPlaying();
            } catch (\Exception $e) {
                throw new \RuntimeException(
                    sprintf(
                        'Could not connect to AzuraCast instance at %s: %s',
                        $value,
                        $e->getMessage() . ' ' . $e->getFile() . ' L' . $e->getLine() . ': ' . $e->getTraceAsString()
                    )
                );
            }

            return $value;
        });

        $baseUrl = $io->askQuestion($question);

        if (empty($baseUrl)) {
            $io->error('You must provide an AzuraCast Base URL to continue.');
            return 1;
        }

        //
        // API Key
        //
        $apiKeyUrl = (string)(new Uri($baseUrl))->withPath('/api_keys');

        $io->section('AzuraCast API Key');
        $io->writeln('You must provide an API key for an authorized account on this installation.');
        $io->writeln('You can generate a new API key at:');
        $io->listing([$apiKeyUrl]);

        $question = new Question\Question('AzuraCast API Key', getenv('AZURACAST_API_KEY'));
        $question->setMaxAttempts(10);
        $question->setValidator(function ($value) use ($baseUrl) {
            $api = Client::create(
                $baseUrl,
                $value,
                $this->guzzleFactory->getDefaultConfig()
            );
            $relays = $api->admin()->relays()->list();

            if (0 === count($relays)) {
                throw new \RuntimeException(
                    'No relayable streams were found on the remote server. Make sure your account has permission to "Manage Broadcasting" for the station you want to relay.'
                );
            }

            return $value;
        });

        $apiKey = $io->askQuestion($question);

        if (empty($apiKey)) {
            $io->error('You must provide an API key to continue.');
            return 1;
        }

        //
        // Relay Name
        //

        $io->section('About This Relay');
        $io->writeln('You can now provide some details about this relay.');
        $io->writeln('These details will be reported back to the parent AzuraCast instance and');
        $io->writeln('displayed to listeners if this relay is public.');

        $question = new Question\Question('Relay Display Name', getenv('AZURARELAY_NAME') ?? 'Relay');
        $relayName = $io->askQuestion($question);

        //
        // Relay Base URL
        //

        $io->writeln('Provide the base URL of that installation (including "http://" or "https://") to continue.');

        $publicIp = @file_get_contents('http://ipecho.net/plain');

        $question = new Question\Question('Relay Base URL', getenv('AZURARELAY_BASE_URL') ?? $publicIp);
        $question->setMaxAttempts(10);
        $question->setValidator(function ($value) {
            $relayBaseUri = new Uri($value);
            if (!in_array($relayBaseUri->getScheme(), ['http', 'https'])) {
                throw new \RuntimeException(
                    sprintf(
                        'The entered URL "%s" must include "http://" or "https://"',
                        $value,
                    )
                );
            }

            return $value;
        });

        $relayBaseUrl = $io->askQuestion($question);

        //
        // Relay is Public
        //

        $question = new Question\ConfirmationQuestion('Show This Relay to AzuraCast Listeners?', getenv('AZURARELAY_IS_PUBLIC') ?? true);
        $relayIsPublic = $io->askQuestion($question);

        $io->section('Generating configuration file...');

        $envFile = [
            '# This file was automatically generated by AzuraRelay. You can modify it if needed.',
            '',
            '# The base URL for the parent AzuraCast instance.',
            Environment::PARENT_BASE_URL.'='.$baseUrl,
            '',
            '# The API key for an authorized user on the parent AzuraCast instance.',
            Environment::PARENT_API_KEY.'='.$apiKey,
            '',
            '# The display name of this relay.',
            Environment::RELAY_NAME.'='.$relayName,
            '',
            '# The base URL of this relay.',
            Environment::RELAY_BASE_URL.'='.$relayBaseUrl,
            '',
            '# Whether this relay is shown to listeners on the parent AzuraCast instance.',
            Environment::RELAY_IS_PUBLIC.'='.($relayIsPublic ? 'true' : 'false'),
            '',
        ];

        $temp_path = $this->environment->getTempDirectory() . '/azurarelay.env';
        file_put_contents($temp_path, implode("\n", $envFile));

        $io->note($envFile);

        $io->success('Setup is complete!');
        return 0;
    }
}
