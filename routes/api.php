<?php

use Helpyard\App\Controllers\CatalogController;
use Helpyard\App\Core\Request;
use Helpyard\App\Core\Router;

$apiRouter = new Router();
$apiRouter->get('/api/v1/products', static function (array $params, Request $request) use ($config) {
    return (new CatalogController($config['database']))->index($params, $request);
});
$apiRouter->get('/api/v1/products/{slug}', static function (array $params, Request $request) use ($config) {
    return (new CatalogController($config['database']))->show($params, $request);
});
$apiRouter->get('/api/v1/categories', static function (array $params, Request $request) use ($config) {
    return (new CatalogController($config['database']))->categories($params, $request);
});

return $apiRouter;
