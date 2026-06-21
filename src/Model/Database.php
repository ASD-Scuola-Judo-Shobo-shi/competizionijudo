<?php

declare(strict_types=1);

namespace App\Model;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $host = env('DB_HOST', '127.0.0.1');
        $name = env('DB_NAME');
        $user = env('DB_USER', 'root');
        $pass = env('DB_PASS', '');

        if ($name === null) {
            throw new \RuntimeException('Missing required environment variable: DB_NAME');
        }

        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $name);
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        self::$pdo = $pdo;

        return self::$pdo;
    }
}
