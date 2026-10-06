<?php

use Helpyard\App\Controllers\CatalogController;
use Helpyard\App\Core\Request;
use Helpyard\App\Core\Response;
use Helpyard\App\Core\Router;

$apiRouter = new Router();
$apiRouter->get('/api/v1/openapi.yaml', static function (array $params, Request $request) {
    $specification = file_get_contents(dirname(__DIR__) . '/docs/openapi.yaml');
    if ($specification === false) {
        return new Response(503, ['Content-Type' => 'application/json; charset=UTF-8'], [
            'success' => false,
            'error' => 'API documentation is temporarily unavailable.',
        ]);
    }

    return new Response(200, ['Content-Type' => 'application/yaml; charset=UTF-8'], $specification);
});
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
