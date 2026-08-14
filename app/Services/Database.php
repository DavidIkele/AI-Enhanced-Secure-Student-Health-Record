<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;

/**
 * PDO connection provider.
 *
 * - PDO exclusively (mysqli etc. are banned across the project).
 * - Exceptions on error; prepared statements are used for all queries.
 * - Emulation of prepared statements is DISABLED so that real prepared
 *   statements (parameterized queries) reach the server.
 *
 * Note: values come from the environment; nothing is hard-coded here.
 */
final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $db = config('db');

        if ($db['name'] === '' || $db['username'] === '') {
            throw new \LogicException(
                'Database configuration is incomplete. Ensure .env provides DB_NAME, DB_USERNAME.'
            );
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $db['host'],
            $db['port'],
            $db['name'],
            $db['charset']
        );

        try {
            self::$connection = new PDO($dsn, $db['username'], $db['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);

            // Ensure connection uses the expected charset.
            self::$connection->exec('SET NAMES ' . $db['charset']);
        } catch (PDOException $e) {
            throw new PDOException(
                'Database connection failed. Check the server and .env configuration.',
                (int) $e->getCode(),
                $e
            );
        }

        return self::$connection;
    }

    public static function isConnected(): bool
    {
        try {
            self::connection()->query('SELECT 1')->fetchColumn();
            return true;
        } catch (\PDOException) {
            return false;
        }
    }

    public static function reset(): void
    {
        self::$connection = null;
    }
}