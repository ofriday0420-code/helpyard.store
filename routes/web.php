<?php

use Helpyard\App\Controllers\StorefrontController;
use Helpyard\App\Core\Router;

$webRouter = new Router();
$storefront = new StorefrontController();
$webRouter->get('/', [$storefront, 'index']);
$webRouter->get('/product/{slug}', [$storefront, 'product']);
$webRouter->get('/products', [$storefront, 'allProducts']);
$webRouter->get('/{section}', [$storefront, 'catalog']);

return $webRouter;
