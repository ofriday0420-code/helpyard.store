<?php

use Helpyard\App\Controllers\StorefrontController;
use Helpyard\App\Controllers\AuthController;
use Helpyard\App\Controllers\CartController;
use Helpyard\App\Controllers\CheckoutController;
use Helpyard\App\Core\Router;

$auth = new AuthController($config['database']);
$cart = new CartController($config['database']);
$checkout = new CheckoutController($config['database']);
$webRouter = new Router();
$webRouter->get('/cart', [$cart, 'show']);
$webRouter->post('/cart/items', [$cart, 'addItem']);
$webRouter->post('/cart/items/{id}/update', [$cart, 'updateItem']);
$webRouter->post('/cart/items/{id}/remove', [$cart, 'removeItem']);
$webRouter->get('/checkout', [$checkout, 'show']);
$webRouter->post('/checkout', [$checkout, 'createOrder']);
$webRouter->get('/orders/{id}', [$checkout, 'showOrder']);
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
