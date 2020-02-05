<?php
namespace App;

use DI;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Invoker;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Slim\Interfaces\CallableResolverInterface;
use Slim\Interfaces\MiddlewareDispatcherInterface;
use Slim\Interfaces\RouteCollectorInterface;
use Slim\Interfaces\RouteResolverInterface;

class AppFactory
{
    /**
     * @param object|null $autoloader The Composer Autoloader object.
     * @param array $appSettings An array of settings to control the application.
     * [
     *    'base_dir' => '/path', // REQUIRED!
     *    'temp_dir' => '/path', // Optional, defaults to $base_dir/../www_tmp
     *    'views_dir' => '/path', // Optional, defaults to $base_dir/templates
     *    'config_dir' => '/path', // Optional, defaults to $base_dir/config
     *    'is_docker' => false, // Default TRUE
     *    ... any other App-specific settings.
     * ],
     * @param array $diDefinitions An array of definitions to supply to the DI container.
     *
     * @return App
     */
    public static function create(
        $autoloader = null,
        $appSettings = [],
        $diDefinitions = []
    ): App {
        $settings = new Settings(self::buildSettings($appSettings));
        Settings::setInstance($settings);

        self::applyPhpSettings($settings);

        $di = self::buildContainer($settings, $diDefinitions);

        // Register static logger.
        Logger::setInstance($di->get(LoggerInterface::class));

        $app = self::createFromContainer($di);
        $di->set(App::class, $app);
        $di->set(\Slim\App::class, $app);

        self::updateRouteHandling($app);
        self::buildRoutes($app);

        return $app;
    }

    /**
     * @param array $settings
     *
     * @return array
     * @throws Exception\BootstrapException
     */
    protected static function buildSettings(array $settings): array
    {
        if (!isset($settings[Settings::BASE_DIR])) {
            throw new Exception\BootstrapException('No base directory specified!');
        }

        if (!isset($settings[Settings::TEMP_DIR])) {
            $settings[Settings::TEMP_DIR] = dirname($settings[Settings::BASE_DIR]) . '/www_tmp';
        }

        if (!isset($settings[Settings::CONFIG_DIR])) {
            $settings[Settings::CONFIG_DIR] = $settings[Settings::BASE_DIR] . '/config';
        }

        if (!isset($settings[Settings::VIEWS_DIR])) {
            $settings[Settings::VIEWS_DIR] = $settings[Settings::BASE_DIR] . '/templates';
        }

        if ($settings[Settings::IS_DOCKER]) {
            $_ENV = getenv();
        } elseif (file_exists($settings[Settings::BASE_DIR] . '/env.ini')) {
            $_ENV = array_merge($_ENV, parse_ini_file($settings[Settings::BASE_DIR] . '/env.ini'));
        }

        if (!isset($settings[Settings::APP_ENV])) {
            $settings[Settings::APP_ENV] = $_ENV['APPLICATION_ENV'] ?? Settings::ENV_PRODUCTION;
        }

        if (isset($_ENV['BASE_URL'])) {
            $settings[Settings::BASE_URL] = $_ENV['BASE_URL'];
        }

        if (file_exists($settings[Settings::CONFIG_DIR] . '/settings.php')) {
            $settingsFile = require($settings[Settings::CONFIG_DIR] . '/settings.php');

            if (is_array($settingsFile)) {
                $settings = array_merge($settings, $settingsFile);
            }
        }

        return $settings;
    }

    /**
     * @param Settings $settings
     */
    protected static function applyPhpSettings(Settings $settings): void
    {
        ini_set('display_startup_errors', !$settings->isProduction() ? 1 : 0);
        ini_set('display_errors', !$settings->isProduction() ? 1 : 0);
        ini_set('log_errors', 1);
        ini_set('error_log',
            $settings[Settings::IS_DOCKER] ? '/dev/stderr' : $settings[Settings::TEMP_DIR] . '/php_errors.log');
        ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_WARNING & ~E_STRICT);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_lifetime', 86400);
        ini_set('session.use_strict_mode', 1);

        session_cache_limiter('');
    }

    /**
     * @param Settings $settings
     * @param array $diDefinitions
     *
     * @return DI\Container
     * @throws \Exception
     */
    protected static function buildContainer(Settings $settings, array $diDefinitions = []): DI\Container
    {
        $containerBuilder = new DI\ContainerBuilder;
        $containerBuilder->useAutowiring(true);

        if ($settings->isProduction()) {
            $containerBuilder->enableCompilation($settings[Settings::TEMP_DIR]);
        }

        if (!isset($diDefinitions[Settings::class])) {
            $diDefinitions[Settings::class] = $settings;
            $diDefinitions['settings'] = DI\Get(Settings::class);
        }

        $containerBuilder->addDefinitions($diDefinitions);

        // Check for services.php file and include it if one exists.
        $config_dir = $settings[Settings::CONFIG_DIR];
        if (file_exists($config_dir . '/services.php')) {
            $containerBuilder->addDefinitions($config_dir . '/services.php');
        }

        return $containerBuilder->build();
    }

    /**
     * @param ContainerInterface $container
     *
     * @return App
     */
    protected static function createFromContainer(ContainerInterface $container): App
    {
        $responseFactory = $container->has(ResponseFactoryInterface::class)
            ? $container->get(ResponseFactoryInterface::class)
            : new Http\Factory\ResponseFactory;

        $callableResolver = $container->has(CallableResolverInterface::class)
            ? $container->get(CallableResolverInterface::class)
            : null;

        $routeCollector = $container->has(RouteCollectorInterface::class)
            ? $container->get(RouteCollectorInterface::class)
            : null;

        $routeResolver = $container->has(RouteResolverInterface::class)
            ? $container->get(RouteResolverInterface::class)
            : null;

        $middlewareDispatcher = $container->has(MiddlewareDispatcherInterface::class)
            ? $container->get(MiddlewareDispatcherInterface::class)
            : null;

        return new App(
            $responseFactory,
            $container,
            $callableResolver,
            $routeCollector,
            $routeResolver,
            $middlewareDispatcher
        );
    }

    /**
     * @param App $app
     */
    protected static function updateRouteHandling(App $app): void
    {
        $di = $app->getContainer();
        $routeCollector = $app->getRouteCollector();

        /** @var Settings $settings */
        $settings = $di->get(Settings::class);

        // Use the PHP-DI Bridge's action invocation helper.
        $resolvers = [
            // Inject parameters by name first
            new Invoker\ParameterResolver\AssociativeArrayResolver(),
            // Then inject services by type-hints for those that weren't resolved
            new Invoker\ParameterResolver\Container\TypeHintContainerResolver($di),
            // Then fall back on parameters default values for optional route parameters
            new Invoker\ParameterResolver\DefaultValueResolver(),
        ];

        $invoker = new Invoker\Invoker(new Invoker\ParameterResolver\ResolverChain($resolvers), $di);
        $controllerInvoker = new DI\Bridge\Slim\ControllerInvoker($invoker);

        $routeCollector->setDefaultInvocationStrategy($controllerInvoker);

        if ($settings->isProduction()) {
            $routeCollector->setCacheFile($settings[Settings::TEMP_DIR] . '/app_routes.cache.php');
        }
    }

    /**
     * @param App $app
     */
    protected static function buildRoutes(App $app): void
    {
        $di = $app->getContainer();

        /** @var EventDispatcher $dispatcher */
        $dispatcher = $di->get(EventDispatcher::class);
        $dispatcher->dispatch(new Event\BuildRoutes($app));
    }
}
