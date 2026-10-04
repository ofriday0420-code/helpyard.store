<?php

spl_autoload_register(static function (string $class): void {
    $prefix = 'Helpyard\\App\\';
    $baseDir = __DIR__ . '/../app/';

    if (str_starts_with($class, $prefix) === false) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

require __DIR__ . '/../config/app.php';
require __DIR__ . '/../config/database.php';
file_put_contents('C:\\temp\\helpyard_request_debug.json', json_encode($_SERVER, JSON_PRETTY_PRINT));

use Helpyard\App\Core\Request;

$request = Request::fromGlobals();

$webRouter = require __DIR__ . '/../routes/web.php';
$apiRouter = require __DIR__ . '/../routes/api.php';
$webRouter->merge($apiRouter);

$response = $webRouter->dispatch($request);
$response->send();
