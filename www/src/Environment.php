<?php

namespace App;

use App\Traits\AvailableStaticallyTrait;

class Environment
{
    use AvailableStaticallyTrait;

    protected array $data = [];

    // Environments
    public const ENV_DEVELOPMENT = 'development';
    public const ENV_TESTING = 'testing';
    public const ENV_PRODUCTION = 'production';

    // Core settings values
    public const APP_NAME = 'APP_NAME';
    public const APP_ENV = 'APPLICATION_ENV';

    public const BASE_DIR = 'BASE_DIR';
    public const TEMP_DIR = 'TEMP_DIR';
    public const CONFIG_DIR = 'CONFIG_DIR';
    public const VIEWS_DIR = 'VIEWS_DIR';

    public const IS_DOCKER = 'IS_DOCKER';
    public const IS_CLI = 'IS_CLI';

    // Default settings
    protected array $defaults = [
        self::APP_NAME => 'AzuraRelay',
        self::APP_ENV => self::ENV_PRODUCTION,

        self::IS_DOCKER => true,
        self::IS_CLI => ('cli' === PHP_SAPI),
    ];

    public function __construct(array $elements = [])
    {
        $this->data = array_merge($this->defaults, $elements);
    }

    public function getAppEnvironment(): string
    {
        return $this->data[self::APP_ENV] ?? self::ENV_PRODUCTION;
    }

    public function isProduction(): bool
    {
        return self::ENV_PRODUCTION === $this->getAppEnvironment();
    }

    public function isTesting(): bool
    {
        return self::ENV_TESTING === $this->getAppEnvironment();
    }

    public function isDevelopment(): bool
    {
        return self::ENV_DEVELOPMENT === $this->getAppEnvironment();
    }

    public function isDocker(): bool
    {
        return (bool)($this->data[self::IS_DOCKER] ?? true);
    }

    public function isCli(): bool
    {
        return $this->data[self::IS_CLI] ?? ('cli' === PHP_SAPI);
    }

    public function getAppName(): string
    {
        return $this->data[self::APP_NAME] ?? 'Application';
    }

    public function getAssetUrl(): ?string
    {
        return $this->data[self::ASSET_URL] ?? '';
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

    /**
     * @return string The parent directory the application is within, i.e. `/var/azuracast`.
     */
    public function getParentDirectory(): string
    {
        return dirname($this->getBaseDirectory());
    }
}
