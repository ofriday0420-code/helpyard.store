<?php

namespace Helpyard\App\Core;

use PDO;
use RuntimeException;

class MigrationRunner
{
    public function __construct(
        private PDO $connection,
        private string $migrationDirectory
    ) {
    }

    public function migrate(): array
    {
        $this->connection->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                checksum CHAR(64) NOT NULL,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $files = glob(rtrim($this->migrationDirectory, '\\/') . DIRECTORY_SEPARATOR . '*.sql');
        if ($files === false) {
            throw new RuntimeException('Could not read the migrations directory.');
        }
        sort($files, SORT_STRING);

        $applied = $this->connection->query('SELECT migration, checksum FROM schema_migrations')
            ->fetchAll(PDO::FETCH_KEY_PAIR);
        $newMigrations = [];

        foreach ($files as $file) {
            $name = basename($file);
            $checksum = hash_file('sha256', $file);
            if ($checksum === false) {
                throw new RuntimeException('Could not calculate migration checksum: ' . $name);
            }

            if (array_key_exists($name, $applied)) {
                if (!hash_equals($applied[$name], $checksum)) {
                    throw new RuntimeException('Applied migration has changed; create a new migration instead: ' . $name);
                }
                continue;
            }

            SqlScript::executeFile($this->connection, $file);
            $record = $this->connection->prepare(
                'INSERT INTO schema_migrations (migration, checksum) VALUES (:migration, :checksum)'
            );
            $record->execute(['migration' => $name, 'checksum' => $checksum]);
            $newMigrations[] = $name;
        }

        return $newMigrations;
    }
}
