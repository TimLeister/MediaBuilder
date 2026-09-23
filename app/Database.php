<?php

declare(strict_types=1);

namespace Media;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host = Config::get('DB_HOST');
        $name = Config::get('DB_NAME');
        $user = Config::get('DB_USER');
        $pass = Config::get('DB_PASS');

        $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";

        try {
            self::$connection = new PDO(
                $dsn,
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Database connection failed.',
                0,
                $e
            );
        }

        return self::$connection;
    }
}
