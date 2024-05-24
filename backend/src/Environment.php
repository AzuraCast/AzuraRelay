<?php

namespace App;

final class Environment
{
    public static function getApplicationEnv(): string
    {
        return $_ENV['APPLICATION_ENV'] ?? 'production';
    }

    public static function isProduction(): bool
    {
        return self::getApplicationEnv() === 'production';
    }

    public static function isDev(): bool
    {
        return self::getApplicationEnv() !== 'production';
    }

    public static function isCli(): bool
    {
        return ('cli' === PHP_SAPI);
    }

    public static function getBaseDirectory(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function getParentDirectory(): string
    {
        return dirname(self::getBaseDirectory());
    }

    public static function getTempDirectory(): string
    {
        return self::getParentDirectory() . '/www_tmp';
    }

    public static function getStationsDirectory(): string
    {
        return self::getParentDirectory() . '/stations';
    }

    public static function getParentBaseUrl(): ?string
    {
        return $_ENV['AZURACAST_BASE_URL'] ?? null;
    }

    public static function getParentApiKey(): ?string
    {
        return $_ENV['AZURACAST_API_KEY'] ?? null;
    }

    public static function getRelayBaseUrl(): ?string
    {
        return $_ENV['AZURARELAY_BASE_URL'] ?? null;
    }

    public static function getRelayName(): ?string
    {
        return $_ENV['AZURARELAY_NAME'] ?? null;
    }

    public static function relayIsPublic(): bool
    {
        return self::envToBool($_ENV['AZURARELAY_IS_PUBLIC'] ?? false);
    }

    protected static function envToBool($value): bool
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
}
