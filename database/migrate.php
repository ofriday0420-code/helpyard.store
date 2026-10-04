<?php

use Helpyard\App\Core\Database;
use Helpyard\App\Core\MigrationRunner;

$config = require __DIR__ . '/../bootstrap.php';

try {
    $connection = Database::connect($config['database']);
    $runner = new MigrationRunner($connection, __DIR__ . '/migrations');
    $migrations = $runner->migrate();

    if ($migrations === []) {
        echo "Database is up to date.\n";
        exit(0);
    }

    foreach ($migrations as $migration) {
        echo 'Applied ' . $migration . PHP_EOL;
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Migration failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
