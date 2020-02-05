<?php
use App\Event;
use App\Console\Command;
use App\Settings;
use App\Middleware;
use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Tools\Console\ConsoleRunner;
use Doctrine\Migrations\Tools\Console\Helper\ConfigurationHelper;
use Doctrine\ORM\EntityManager;
use Slim\Interfaces\ErrorHandlerInterface;

return function (\App\EventDispatcher $dispatcher)
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

        /** @var Settings $settings */
        $settings = $container->get(Settings::class);

        if (file_exists($settings[Settings::CONFIG_DIR] . '/routes.php')) {
            call_user_func(include($settings[Settings::CONFIG_DIR] . '/routes.php'), $app);
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
        $errorMiddleware = $app->addErrorMiddleware(!$settings->isProduction(), true, true);
        $errorMiddleware->setDefaultErrorHandler(ErrorHandlerInterface::class);
    });
};
