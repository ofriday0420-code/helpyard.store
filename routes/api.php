<?php

use Helpyard\App\Controllers\ProductController;
use Helpyard\App\Core\Router;

$apiRouter = new Router();
$apiRouter->get('/api/v1/products', [ProductController::class, 'index']);
$apiRouter->get('/api/v1/categories', static function (): array {
    return [
        'success' => true,
        'categories' => [
            ['id' => 1, 'name' => 'Courses'],
            ['id' => 2, 'name' => 'Ready Websites'],
            ['id' => 3, 'name' => 'Official Software'],
            ['id' => 4, 'name' => 'Books'],
            ['id' => 5, 'name' => 'Cyber-Security Devices'],
        ],
    ];
});

return $apiRouter;
