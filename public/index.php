<?php

use Helpyard\App\Core\Request;

$config = require __DIR__ . '/../bootstrap.php';

$request = Request::fromGlobals();

$webRouter = require __DIR__ . '/../routes/web.php';
$apiRouter = require __DIR__ . '/../routes/api.php';
$webRouter->merge($apiRouter);

$response = $webRouter->dispatch($request);
$response->send();
