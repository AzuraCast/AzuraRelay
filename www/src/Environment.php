<?php

namespace App;

class Environment
{
    /** @var static */
    protected static $instance;

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

    public const IS_CLI = 'IS_CLI';

    public const PARENT_BASE_URL = 'AZURACAST_BASE_URL';
    public const PARENT_API_KEY = 'AZURACAST_API_KEY';

    public const RELAY_BASE_URL = 'AZURARELAY_BASE_URL';
    public const RELAY_NAME = 'AZURARELAY_NAME';
    public const RELAY_IS_PUBLIC = 'AZURARELAY_IS_PUBLIC';

    // Default settings
    protected array $defaults = [
        self::APP_NAME => 'AzuraRelay',
        self::APP_ENV => self::ENV_PRODUCTION,

        self::IS_CLI => ('cli' === PHP_SAPI),

        self::RELAY_NAME => 'AzuraRelay',
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

    public function isCli(): bool
    {
        return $this->data[self::IS_CLI] ?? ('cli' === PHP_SAPI);
    }

    public function getAppName(): string
    {
        return $this->data[self::APP_NAME] ?? 'Application';
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
     * @return string The parent directory the application is within, i.e. `/var/azuracast`.
     */
    public function getParentDirectory(): string
    {
        return dirname($this->getBaseDirectory());
    }

    public function getParentBaseUrl(): ?string
    {
        return $this->data[self::PARENT_BASE_URL] ?? null;
    }

    public function getParentApiKey(): ?string
    {
        return $this->data[self::PARENT_API_KEY] ?? null;
    }

    public function getRelayBaseUrl(): ?string
    {
        return $this->data[self::RELAY_BASE_URL] ?? null;
    }

    public function getRelayName(): ?string
    {
        return $this->data[self::RELAY_NAME] ?? null;
    }

    public function relayIsPublic(): bool
    {
        return self::envToBool($this->data[self::RELAY_IS_PUBLIC] ?? false);
    }

    public static function envToBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return 0 !== $value;
        }
        if (null === $value) {
            return false;
        }

        $value = (string)$value;
        return str_starts_with(strtolower($value), 'y')
            || 'true' === strtolower($value)
            || '1' === $value;
    }

    /**
     * @return static
     */
    public static function getInstance(): static
    {
        return self::$instance;
    }

    /**
     */
    public static function hasInstance(): bool
    {
        return isset(self::$instance);
    }

    /**
     * @param static $instance
     */
    public static function setInstance($instance): void
    {
        self::$instance = $instance;
    }
}
