<?php

declare(strict_types=1);

namespace App\Support;

use WilyCode\Database\DriverPdo\PdoDatabase;

final class DatabaseFactory
{
    public static function connect(): PdoDatabase
    {
        $host = Environment::get('DB_HOST', '127.0.0.1');
        $port = Environment::get('DB_PORT', '3306');
        $database = Environment::get('DB_DATABASE', 'wily_starter');
        $username = Environment::get('DB_USERNAME', 'wily');
        $password = Environment::get('DB_PASSWORD');

        if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
            throw new \InvalidArgumentException('DB_PORT must be between 1 and 65535.');
        }

        if ($database === '' || !preg_match('/\A[a-zA-Z0-9_]+\z/D', $database)) {
            throw new \InvalidArgumentException('Invalid database name.');
        }

        if ($host === '' || preg_match('/[;\r\n]/', $host)) {
            throw new \InvalidArgumentException('Invalid DB_HOST.');
        }

        return PdoDatabase::connect(
            'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $database . ';charset=utf8mb4',
            $username,
            $password,
        );
    }
}
