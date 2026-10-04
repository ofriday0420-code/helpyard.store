<?php

namespace Helpyard\App\Controllers;

use Helpyard\App\Core\Response;

class ProductController
{
    public function index(array $params = []): Response
    {
        $products = [
            [
                'id' => 1,
                'name' => 'Cyber Security Shield',
                'slug' => 'cyber-security-shield',
                'product_type' => 'device',
                'price' => 129.00,
                'currency' => 'BDT',
            ],
            [
                'id' => 2,
                'name' => 'Helpyard Website Starter',
                'slug' => 'helpyard-website-starter',
                'product_type' => 'website',
                'price' => 349.00,
                'currency' => 'BDT',
            ],
            [
                'id' => 3,
                'name' => 'Beginner PHP E-commerce Guide',
                'slug' => 'beginner-php-ecommerce-guide',
                'product_type' => 'book',
                'price' => 89.00,
                'currency' => 'BDT',
            ],
        ];

        return new Response(200, ['Content-Type' => 'application/json; charset=UTF-8'], [
            'success' => true,
            'count' => count($products),
            'products' => $products,
        ]);
    }
}
