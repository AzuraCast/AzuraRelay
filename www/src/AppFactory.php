<?php

namespace App;

use DI;
use DI\Bridge\Slim\ControllerInvoker;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Http\Factory\Guzzle\ResponseFactory;
use Http\Factory\Guzzle\StreamFactory;
use Invoker\Invoker;
use Invoker\ParameterResolver\AssociativeArrayResolver;
use Invoker\ParameterResolver\Container\TypeHintContainerResolver;
use Invoker\ParameterResolver\DefaultValueResolver;
use Invoker\ParameterResolver\ResolverChain;
use Monolog\Registry;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Http\Factory\DecoratedResponseFactory;
use Symfony\Component\Console\Application;

class AppFactory
{
    public static function createApp($autoloader = null, $appEnvironment = [], $diDefinitions = []): App
    {
        $di = self::buildContainer($autoloader, $appEnvironment, $diDefinitions);
        return self::buildAppFromContainer($di);
    }

    public static function createCli($autoloader = null, $appEnvironment = [], $diDefinitions = []): Application
    {
        $di = self::buildContainer($autoloader, $appEnvironment, $diDefinitions);
        self::buildAppFromContainer($di);

        return $di->get(Application::class);
    }

    public static function buildAppFromContainer(DI\Container $container): App
    {
        $app = new App(
            new DecoratedResponseFactory(new ResponseFactory(), new StreamFactory()),
            $container
        );
        $container->set(App::class, $app);

        $routeCollector = $app->getRouteCollector();

        // Use the PHP-DI Bridge's action invocation helper.
        $resolvers = [
            // Inject parameters by name first
            new AssociativeArrayResolver(),
            // Then inject services by type-hints for those that weren't resolved
            new TypeHintContainerResolver($container),
            // Then fall back on parameters default values for optional route parameters
            new DefaultValueResolver(),
        ];

        $invoker = new Invoker(new ResolverChain($resolvers), $container);
        $controllerInvoker = new ControllerInvoker($invoker);

        $routeCollector->setDefaultInvocationStrategy($controllerInvoker);

        $environment = $container->get(Environment::class);

        // Build routes
        if (file_exists($environment->getConfigDirectory() . '/routes.php')) {
            call_user_func(include($environment->getConfigDirectory() . '/routes.php'), $app);
        }

        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();

        $app->addErrorMiddleware(
            !$environment->isProduction(),
            true,
            true,
            $container->get(LoggerInterface::class)
        );

        return $app;
    }

    public static function buildContainer(
        $autoloader = null,
        $appEnvironment = [],
        $diDefinitions = []
    ): DI\Container {
        // Register Annotation autoloader
        if (null !== $autoloader) {
            AnnotationRegistry::registerLoader([$autoloader, 'loadClass']);
        }

        $environment = self::buildEnvironment($appEnvironment);
        Environment::setInstance($environment);

        $diDefinitions[Environment::class] = $environment;

        self::applyPhpSettings($environment);

        $containerBuilder = new DI\ContainerBuilder();
        $containerBuilder->useAnnotations(true);
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

        $environment[Environment::IS_DOCKER] = true;

        $environment[Environment::TEMP_DIR] ??= dirname($environment[Environment::BASE_DIR]) . '/www_tmp';
        $environment[Environment::CONFIG_DIR] ??= $environment[Environment::BASE_DIR] . '/config';
        $environment[Environment::VIEWS_DIR] ??= $environment[Environment::BASE_DIR] . '/templates';

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
            $environment->isDocker()
                ? '/dev/stderr'
                : $environment->getTempDirectory() . '/php_errors.log'
        );
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_lifetime', '86400');
        ini_set('session.use_strict_mode', '1');

        date_default_timezone_set('UTC');

        session_cache_limiter('');
    }
}
