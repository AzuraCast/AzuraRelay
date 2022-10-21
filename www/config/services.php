<?php

return [
    // HTTP client
    GuzzleHttp\HandlerStack::class => function (Psr\Log\LoggerInterface $logger) {
        $stack = GuzzleHttp\HandlerStack::create();

        $stack->push(
            GuzzleHttp\Middleware::log(
                $logger,
                new GuzzleHttp\MessageFormatter('HTTP client {method} call to {uri} produced response {code}'),
                Psr\Log\LogLevel::DEBUG
            )
        );

        return $stack;
    },

    GuzzleHttp\Client::class => function (GuzzleHttp\HandlerStack $handlers) {
        return new GuzzleHttp\Client(
            [
                'handler' => $handlers,
                GuzzleHttp\RequestOptions::VERIFY => false,
                GuzzleHttp\RequestOptions::HTTP_ERRORS => false,
                GuzzleHttp\RequestOptions::TIMEOUT => 5.0,
            ]
        );
    },

    // Console
    Symfony\Component\Console\Application::class => function (DI\Container $di, Psr\Log\LoggerInterface $logger) {
        $eventDispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();
        $eventDispatcher->addSubscriber(new App\Console\ErrorHandler($logger));

        $console = new Symfony\Component\Console\Application(
            'AzuraRelay Command Line Utility',
            '1.0.0'
        );
        $console->setDispatcher($eventDispatcher);

        $commandLoader = new Symfony\Component\Console\CommandLoader\ContainerCommandLoader(
            $di,
            [
                'app:nowplaying' => App\Console\Command\NowPlayingCommand::class,
                'app:setup' => App\Console\Command\SetupCommand::class,
                'app:update' => App\Console\Command\UpdateCommand::class,
                'app:acme' => App\Console\Command\Acme\GetCertificateCommand::class,
            ]
        );
        $console->setCommandLoader($commandLoader);

        return $console;
    },

    // Monolog Logger
    Monolog\Logger::class => function (App\Environment $environment) {
        $logger = new Monolog\Logger($environment->getAppName());

        $loggingLevel = $environment->isProduction() ? Monolog\Level::Notice : Monolog\Level::Debug;

        $log_stderr = new Monolog\Handler\StreamHandler('php://stderr', $loggingLevel, true);
        $logger->pushHandler($log_stderr);

        $log_file = new Monolog\Handler\StreamHandler(
            $environment->getTempDirectory() . '/app.log',
            $loggingLevel,
            true
        );
        $logger->pushHandler($log_file);

        return $logger;
    },
    Psr\Log\LoggerInterface::class => DI\get(Monolog\Logger::class),

    AzuraCast\Api\Client::class => function (GuzzleHttp\HandlerStack $handlers) {
        return AzuraCast\Api\Client::create(
            getenv('AZURACAST_BASE_URL'),
            getenv('AZURACAST_API_KEY'),
            new GuzzleHttp\Client(
                [
                    'handler' => $handlers,
                    GuzzleHttp\RequestOptions::VERIFY => false,
                    GuzzleHttp\RequestOptions::HTTP_ERRORS => false,
                    GuzzleHttp\RequestOptions::TIMEOUT => 15.0,
                ]
            )
        );
    },

    Supervisor\Supervisor::class => function() {
        $client = new fXmlRpc\Client(
            'http://localhost/RPC2',
            new fXmlRpc\Transport\PsrTransport(
                new GuzzleHttp\Psr7\HttpFactory,
                new GuzzleHttp\Client([
                                          'curl' => [
                                              \CURLOPT_UNIX_SOCKET_PATH => '/tmp/supervisor.sock',
                                          ],
                                      ])
            )
        );

        $supervisor = new Supervisor\Supervisor($client);

        if (!$supervisor->isConnected()) {
            throw new RuntimeException(sprintf('Could not connect to supervisord.'));
        }

        return $supervisor;
    },

    // NowPlaying Adapter factory
    NowPlaying\AdapterFactory::class => function (
        GuzzleHttp\Client $client,
        Psr\Log\LoggerInterface $logger
    ) {
        return new NowPlaying\AdapterFactory(
            new GuzzleHttp\Psr7\HttpFactory,
            new GuzzleHttp\Psr7\HttpFactory,
            $client,
            $logger
        );
    },
];
