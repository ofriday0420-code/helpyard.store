<?php

use Helpyard\App\Controllers\StorefrontController;
use Helpyard\App\Controllers\AuthController;
use Helpyard\App\Core\Router;

$auth = new AuthController($config['database']);
$webRouter = new Router();
$webRouter->get('/register', [$auth, 'registerForm']);
$webRouter->post('/register', [$auth, 'register']);
$webRouter->get('/login', [$auth, 'loginForm']);
$webRouter->post('/login', [$auth, 'login']);
$webRouter->post('/logout', [$auth, 'logout']);
$webRouter->get('/account', [$auth, 'account']);
$webRouter->post('/account/profile', [$auth, 'updateProfile']);
$webRouter->get('/account/addresses', [$auth, 'addresses']);
$webRouter->post('/account/addresses', [$auth, 'addAddress']);
$webRouter->post('/account/addresses/{id}/delete', [$auth, 'deleteAddress']);
$storefront = new StorefrontController();
$webRouter->get('/', [$storefront, 'index']);
$webRouter->get('/product/{slug}', [$storefront, 'product']);
$webRouter->get('/products', [$storefront, 'allProducts']);
$webRouter->get('/{section}', [$storefront, 'catalog']);

return $webRouter;
