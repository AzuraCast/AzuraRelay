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

    // Controller groups
    $di->register(new \App\Provider\FrontendProvider);

    return $di;
};
