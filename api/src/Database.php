<?php

declare(strict_types=1);

namespace PracticeApi;

use PDO;

final class Database
{
    public static function connect(Env $env): PDO
    {
        $driver = $env->get('DB_CONNECTION', 'mysql');
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if ($driver !== 'mysql' && $driver !== 'mariadb') {
            throw new \RuntimeException("Unsupported DB_CONNECTION '{$driver}'.");
        }
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $env->get('DB_HOST', '127.0.0.1'),
            $env->get('DB_PORT', '3306'),
            $env->get('DB_DATABASE', 'pitchwise'),
        );
        $pdo = new PDO($dsn, $env->get('DB_USERNAME', 'root'), $env->get('DB_PASSWORD', ''), $options);
        $pdo->exec("SET time_zone = '+00:00'");

        return $pdo;
    }
}
