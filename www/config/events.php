<?php
use App\Event;
use App\Environment;
use App\Middleware;

return function (App\EventDispatcher $dispatcher)
{
    $dispatcher->addListener(Event\BuildConsoleCommands::class, function(Event\BuildConsoleCommands $event) {
        $console = $event->getConsole();

        if (file_exists(__DIR__ . '/cli.php')) {
            call_user_func(include(__DIR__ . '/cli.php'), $console);
        }
    });

    $dispatcher->addListener(Event\BuildRoutes::class, function(Event\BuildRoutes $event) {
        $app = $event->getApp();

        // Load app-specific route configuration.
        $container = $app->getContainer();

        /** @var Environment $environment */
        $environment = $container->get(Environment::class);

        if (file_exists($environment->getConfigDirectory() . '/routes.php')) {
            call_user_func(include($environment->getConfigDirectory() . '/routes.php'), $app);
        }

        // Request injection middlewares.
        $app->add(Middleware\InjectRouter::class);

        // System middleware for routing and body parsing.
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();

        // Redirects and updates that should happen before system middleware.
        $app->add(new Middleware\RemoveSlashes);
        $app->add(new Middleware\ApplyXForwardedProto);

        // Error handling, which should always be near the "last" element.
        $logger = $container->get(Psr\Log\LoggerInterface::class);

        $app->addErrorMiddleware(
            !$environment->isProduction(),
            true,
            true,
            $logger
        );
    });
};
