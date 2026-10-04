<?php

use Helpyard\App\Core\Environment;

spl_autoload_register(static function (string $class): void {
    $prefix = 'Helpyard\\App\\';
    $baseDir = __DIR__ . '/app/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

Environment::load(__DIR__ . '/.env');

return [
    'app' => require __DIR__ . '/config/app.php',
    'database' => require __DIR__ . '/config/database.php',
];
