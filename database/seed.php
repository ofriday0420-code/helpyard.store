<?php

use Helpyard\App\Core\Database;
use Helpyard\App\Core\SqlScript;

$config = require __DIR__ . '/../bootstrap.php';

try {
    $connection = Database::connect($config['database']);
    $seedFiles = glob(__DIR__ . '/seeders/*.sql');
    if ($seedFiles === false) {
        throw new RuntimeException('Could not read the seeders directory.');
    }
    sort($seedFiles, SORT_STRING);

    foreach ($seedFiles as $file) {
        SqlScript::executeFile($connection, $file);
        echo 'Applied ' . basename($file) . PHP_EOL;
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Seeding failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
