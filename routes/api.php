<?php

use Helpyard\App\Controllers\CatalogController;
use Helpyard\App\Controllers\CustomerApiController;
use Helpyard\App\Core\Request;
use Helpyard\App\Core\Response;
use Helpyard\App\Core\Router;

$apiRouter = new Router();
$customerApi = new CustomerApiController($config['database']);
$apiRouter->post('/api/v1/auth/token', [$customerApi, 'issueToken']);
$apiRouter->post('/api/v1/auth/revoke', [$customerApi, 'revokeToken']);
$apiRouter->get('/api/v1/auth/tokens', [$customerApi, 'tokens']);
$apiRouter->delete('/api/v1/auth/tokens/{id}', [$customerApi, 'revokeNamedToken']);
$apiRouter->get('/api/v1/account', [$customerApi, 'account']);
$apiRouter->get('/api/v1/orders', [$customerApi, 'orders']);
$apiRouter->get('/api/v1/orders/{id}', [$customerApi, 'order']);
$apiRouter->get('/api/v1/courses', [$customerApi, 'courses']);
$apiRouter->get('/api/v1/courses/{id}', [$customerApi, 'course']);
$apiRouter->post('/api/v1/courses/{id}/lessons/{lesson_id}/complete', [$customerApi, 'completeLesson']);
$apiRouter->get('/api/v1/cart', [$customerApi, 'cart']);
$apiRouter->post('/api/v1/cart/items', [$customerApi, 'addCartItem']);
$apiRouter->patch('/api/v1/cart/items/{id}', [$customerApi, 'updateCartItem']);
$apiRouter->delete('/api/v1/cart/items/{id}', [$customerApi, 'removeCartItem']);
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
