<?php

use Helpyard\App\Controllers\HomeController;
use Helpyard\App\Controllers\CatalogController;
use Helpyard\App\Core\Request;
use Helpyard\App\Core\Router;

$webRouter = new Router();
$webRouter->get('/', [HomeController::class, 'index']);
$webRouter->get('/products', static function (array $params, Request $request) use ($config) {
    return (new CatalogController($config['database']))->index($params, $request);
});

return $webRouter;
