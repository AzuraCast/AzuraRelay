<?php
return function (\Azura\Container $di)
{
    $di->extend(\Azura\Console\Application::class, function(\Azura\Console\Application $console, $di) {
        $console->setName('AzuraRelay Command Line Utility');
    });

    // Controller groups
    $di->register(new \App\Provider\FrontendProvider);

    return $di;
};
