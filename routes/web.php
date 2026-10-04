<?php

use Helpyard\App\Controllers\HomeController;
use Helpyard\App\Controllers\ProductController;
use Helpyard\App\Core\Router;

$webRouter = new Router();
$webRouter->get('/', [HomeController::class, 'index']);
$webRouter->get('/products', [ProductController::class, 'index']);

return $webRouter;
