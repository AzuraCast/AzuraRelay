<?php
namespace App;

use App\Traits\AvailableStaticallyTrait;

class Settings extends Collection
{
    use AvailableStaticallyTrait;

    // Environments
    public const ENV_DEVELOPMENT = 'development';
    public const ENV_TESTING = 'testing';
    public const ENV_PRODUCTION = 'production';

    // Core settings values
    public const APP_NAME = 'name';
    public const APP_ENV = 'app_env';

    public const BASE_DIR = 'base_dir';
    public const TEMP_DIR = 'temp_dir';
    public const CONFIG_DIR = 'config_dir';
    public const VIEWS_DIR = 'views_dir';
    public const DOCTRINE_OPTIONS = 'doctrine_options';
    public const IS_DOCKER = 'is_docker';
    public const IS_CLI = 'is_cli';

    public const BASE_URL = 'base_url';
    public const ASSETS_URL = 'assets_url';

    public const ENABLE_DATABASE = 'enable_database';
    public const ENABLE_REDIS = 'enable_redis';

    // Default settings
    protected $data = [
        self::APP_NAME => 'Application',
        self::APP_ENV => self::ENV_PRODUCTION,

        self::IS_DOCKER => true,
        self::IS_CLI => ('cli' === PHP_SAPI),

        self::ASSETS_URL => '/static',

        self::ENABLE_DATABASE => true,
        self::ENABLE_REDIS => true,
    ];

    public function isProduction(): bool
    {
        if (isset($this->data[self::APP_ENV])) {
            return (self::ENV_PRODUCTION === $this->data[self::APP_ENV]);
        }
        return true;
    }

    public function isTesting(): bool
    {
        if (isset($this->data[self::APP_ENV])) {
            return (self::ENV_TESTING === $this->data[self::APP_ENV]);
        }
        return false;
    }

    public function isDocker(): bool
    {
        return (bool)($this->data[self::IS_DOCKER] ?? true);
    }

    public function isCli(): bool
    {
        return $this->data[self::IS_CLI] ?? ('cli' === PHP_SAPI);
    }

    public function enableDatabase(): bool
    {
        return (bool)($this->data[self::ENABLE_DATABASE] ?? true);
    }

    public function enableRedis(): bool
    {
        return (bool)($this->data[self::ENABLE_REDIS] ?? true);
    }

    /**
     * @return string The base directory of the application, i.e. `/var/app/www` for Docker installations.
     */
    public function getBaseDirectory(): string
    {
        return $this->data[self::BASE_DIR];
    }

    /**
     * @return string The directory where temporary files are stored by the application, i.e. `/var/app/www_tmp`
     */
    public function getTempDirectory(): string
    {
        return $this->data[self::TEMP_DIR];
    }

    /**
     * @return string The directory where configuration files are stored by default.
     */
    public function getConfigDirectory(): string
    {
        return $this->data[self::CONFIG_DIR];
    }

    /**
     * @return string The directory where template/view files are stored.
     */
    public function getViewsDirectory(): string
    {
        return $this->data[self::VIEWS_DIR];
    }
}