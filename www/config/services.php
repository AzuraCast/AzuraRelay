<?php
return function (\Azura\Container $di)
{
    $di->extend(\Azura\Console\Application::class, function(\Azura\Console\Application $console, $di) {
        $console->setName('AzuraRelay Command Line Utility');
    });

    $di[\App\Service\AzuraCast::class] = function($di) {
        return new \App\Service\AzuraCast(
            $di[\GuzzleHttp\Client::class],
            $di[\Monolog\Logger::class]
        );
    };

    // Controller groups
    $di->register(new \App\Provider\FrontendProvider);

    return $di;
};
