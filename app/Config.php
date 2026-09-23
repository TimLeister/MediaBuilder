<?php

declare(strict_types=1);

namespace Media;

use Dotenv\Dotenv;
use RuntimeException;

final class Config
{
    private static bool $loaded = false;

    public static function load(string $basePath): void
    {
        if (self::$loaded) {
            return;
        }

        $dotenv = Dotenv::createImmutable($basePath);
        $dotenv->load();

        self::$loaded = true;
    }

    public static function get(string $key, ?string $default = null): string
    {
        $value = $_ENV[$key] ?? $default;

        if ($value === null) {
            throw new RuntimeException(
                "Missing environment variable: {$key}"
            );
        }

        return $value;
    }
}
