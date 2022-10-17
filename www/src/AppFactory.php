<?php

namespace App;

use DI;
use Monolog\Registry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;

class AppFactory
{
    public static function createCli($appEnvironment = [], $diDefinitions = []): Application
    {
        return self::buildContainer($appEnvironment, $diDefinitions)
            ->get(Application::class);
    }

    public static function buildContainer(
        $appEnvironment = [],
        $diDefinitions = []
    ): DI\Container {
        $environment = self::buildEnvironment($appEnvironment);
        Environment::setInstance($environment);

        $diDefinitions[Environment::class] = $environment;

        self::applyPhpSettings($environment);

        $containerBuilder = new DI\ContainerBuilder();
        $containerBuilder->useAutowiring(true);

        if ($environment->isProduction()) {
            $containerBuilder->enableCompilation($environment->getTempDirectory());
        }

        $containerBuilder->addDefinitions($diDefinitions);

        // Check for services.php file and include it if one exists.
        $config_dir = $environment->getConfigDirectory();
        if (file_exists($config_dir . '/services.php')) {
            $containerBuilder->addDefinitions($config_dir . '/services.php');
        }

        $di = $containerBuilder->build();

        $logger = $di->get(LoggerInterface::class);

        register_shutdown_function(
            function (LoggerInterface $logger): void {
                $error = error_get_last();
                if (null === $error) {
                    return;
                }

                $errno = $error["type"] ?? \E_ERROR;
                $errfile = $error["file"] ?? 'unknown';
                $errline = $error["line"] ?? 0;
                $errstr = $error["message"] ?? 'Shutdown';

                if ($errno &= \E_PARSE | \E_ERROR | \E_USER_ERROR | \E_CORE_ERROR | \E_COMPILE_ERROR) {
                    $logger->critical(
                        sprintf(
                            'Fatal error: %s in %s on line %d',
                            $errstr,
                            $errfile,
                            $errline
                        )
                    );
                }
            },
            $logger
        );

        Registry::addLogger($logger, 'app', true);

        return $di;
    }

    protected static function buildEnvironment(array $environment): Environment
    {
        if (!isset($environment[Environment::BASE_DIR])) {
            throw new \RuntimeException('No base directory specified!');
        }

        $environment[Environment::TEMP_DIR] ??= dirname($environment[Environment::BASE_DIR]) . '/www_tmp';
        $environment[Environment::CONFIG_DIR] ??= $environment[Environment::BASE_DIR] . '/config';

        $environment = array_merge(array_filter(getenv()), $environment);

        return new Environment($environment);
    }

    protected static function applyPhpSettings(Environment $environment): void
    {
        error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_STRICT & ~E_DEPRECATED);

        $displayStartupErrors = (!$environment->isProduction() || $environment->isCli())
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
