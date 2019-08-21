<?php
return [
    Azura\Console\Application::class => DI\decorate(function(Azura\Console\Application $console, Psr\Container\ContainerInterface $di) {
        $console->setName('AzuraRelay Command Line Utility');
        return $console;
    }),

    AzuraCast\Api\Client::class => function(GuzzleHttp\Client $httpClient) {
        return AzuraCast\Api\Client::create(
            getenv('AZURACAST_BASE_URL'),
            getenv('AZURACAST_API_KEY'),
            $httpClient
        );
    },

    Supervisor\Supervisor::class => function() {
        $guzzle_client = new \GuzzleHttp\Client();
        $client = new \fXmlRpc\Client(
            'http://127.0.0.1:9001/RPC2',
            new \fXmlRpc\Transport\HttpAdapterTransport(
                new \Http\Message\MessageFactory\GuzzleMessageFactory(),
                new \Http\Adapter\Guzzle6\Client($guzzle_client)
            )
        );

        $connector = new \Supervisor\Connector\XmlRpc($client);
        $supervisor = new \Supervisor\Supervisor($connector);

        if (!$supervisor->isConnected()) {
            throw new \Azura\Exception(sprintf('Could not connect to supervisord.'));
        }

        return $supervisor;
    },
];
