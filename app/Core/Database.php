<?php

namespace Helpyard\App\Core;

use PDO;
use RuntimeException;

class Database
{
    public static function connect(array $config): PDO
    {
        if (($config['driver'] ?? null) !== 'mysql') {
            throw new RuntimeException('Only the MySQL database driver is currently supported.');
        }

        if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
            throw new RuntimeException('The PDO MySQL extension is not enabled in this PHP installation.');
        }

        $host = $config['host'] ?? '';
        $port = $config['port'] ?? '';
        $database = $config['database'] ?? '';
        if ($host === '' || $port === '' || $database === '') {
            throw new RuntimeException('Database host, port, and database name must be configured.');
        }
        if (strpbrk($host, ";\0\r\n") !== false
            || !ctype_digit((string) $port)
            || (int) $port < 1
            || (int) $port > 65535
            || !preg_match('/^[A-Za-z0-9_$]+$/', $database)
        ) {
            throw new RuntimeException('Database host, port, or database name contains an invalid value.');
        }

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);

        $connection = new PDO($dsn, $config['username'] ?? '', $config['password'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $connection->exec("SET time_zone = '+00:00'");

        return $connection;
    }
}
