<?php

namespace App;

use DI\ContainerBuilder;
use Monolog\ErrorHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application as ConsoleApplication;

class AppFactory
{
    public static function createCli(): ConsoleApplication
    {
        self::applyPhpSettings();
        $di = self::buildContainer();

        return $di->get(ConsoleApplication::class);
    }

    public static function buildContainer(): ContainerInterface
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->useAutowiring(true);
        $containerBuilder->useAttributes(true);

        if (Environment::isProduction()) {
            $containerBuilder->enableCompilation(Environment::getTempDirectory());
        }

        $containerBuilder->addDefinitions(dirname(__DIR__) . '/config/services.php');

        $di = $containerBuilder->build();

        // Monolog setup
        $logger = $di->get(Logger::class);

        $errorHandler = new ErrorHandler($logger);
        $errorHandler->registerFatalHandler();

        return $di;
    }

    protected static function applyPhpSettings(): void
    {
        $_ENV = getenv();

        error_reporting(
            Environment::isProduction()
                ? E_ALL & ~E_NOTICE & ~E_WARNING & ~E_STRICT & ~E_DEPRECATED
                : E_ALL & ~E_NOTICE
        );

        $displayStartupErrors = (!Environment::isProduction() || Environment::isCli())
            ? '1'
            : '0';
        ini_set('display_startup_errors', $displayStartupErrors);
        ini_set('display_errors', $displayStartupErrors);

        ini_set('log_errors', '1');
        ini_set(
            'error_log',
            '/dev/stderr'
        );
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_lifetime', '86400');
        ini_set('session.use_strict_mode', '1');

        date_default_timezone_set('UTC');

        session_cache_limiter('');
    }
}
