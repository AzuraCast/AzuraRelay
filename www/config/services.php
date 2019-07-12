<?php
return function (\Azura\Container $di)
{
    $di->extend(\Azura\Console\Application::class, function(\Azura\Console\Application $console, $di) {
        $console->setName('AzuraRelay Command Line Utility');
        return $console;
    });

    $di[\AzuraCast\Api\Client::class] = function($di) {
        /** @var \GuzzleHttp\Client $diClient */
        $httpClient = $di[\GuzzleHttp\Client::class];

        return \AzuraCast\Api\Client::create(
            getenv('AZURACAST_BASE_URL'),
            getenv('AZURACAST_API_KEY'),
            $httpClient
        );
    };

    $di[\Supervisor\Supervisor::class] = function ($di) {
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
    };

    // Controller groups
    $di->register(new \App\Provider\FrontendProvider);

    return $di;
};
