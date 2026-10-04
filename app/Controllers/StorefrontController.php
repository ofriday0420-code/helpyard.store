<?php

namespace Helpyard\App\Controllers;

use Helpyard\App\Core\Response;
use RuntimeException;

class StorefrontController
{
    public function index(array $params = []): Response
    {
        return $this->render('home.php', [
            'title' => 'Useful digital products. One trusted place.',
            'description' => 'Explore practical courses, websites, software, books, and security devices from Helpyard.store.',
        ]);
    }

    public function catalog(array $params = []): Response
    {
        $sections = [
            'courses' => [
                'title' => 'Courses',
                'description' => 'Learn practical skills with courses selected for your next step.',
                'type' => 'course',
                'category' => 'courses',
            ],
            'websites' => [
                'title' => 'Ready Websites',
                'description' => 'Explore ready-to-use website packages for your next project.',
                'type' => 'website',
                'category' => 'ready-websites',
            ],
            'software' => [
                'title' => 'Official Software',
                'description' => 'Browse software products for work, learning, and everyday use.',
                'type' => 'software',
                'category' => 'official-software',
            ],
            'books' => [
                'title' => 'Books',
                'description' => 'Find useful reading for your work and personal growth.',
                'type' => 'book',
                'category' => 'books',
            ],
            'devices' => [
                'title' => 'Cyber-Security Devices',
                'description' => 'Explore security-focused devices and hardware.',
                'type' => 'physical',
                'category' => 'cyber-security-devices',
            ],
        ];

        $section = $params['section'] ?? '';
        if (!isset($sections[$section])) {
            return $this->notFound();
        }

        return $this->render('catalog.php', [
            ...$sections[$section],
            'description' => $sections[$section]['description'],
        ]);
    }

    public function allProducts(array $params = []): Response
    {
        return $this->render('catalog.php', [
            'title' => 'All products',
            'description' => 'Browse courses, ready websites, software, books, and security devices together.',
            'type' => '',
            'category' => '',
        ]);
    }

    public function product(array $params = []): Response
    {
        $slug = $params['slug'] ?? '';
        if (!is_string($slug) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return $this->notFound();
        }

        return $this->render('product.php', [
            'slug' => $slug,
            'title' => 'Product details',
            'description' => 'View product details from Helpyard.store.',
        ]);
    }

    private function render(string $template, array $data): Response
    {
        $path = __DIR__ . '/../Views/' . $template;
        if (!is_file($path)) {
            throw new RuntimeException('Storefront template is missing: ' . $template);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $path;
        $html = ob_get_clean();
        if ($html === false) {
            throw new RuntimeException('Could not render storefront template: ' . $template);
        }

        return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $html);
    }

    private function notFound(): Response
    {
        return new Response(404, ['Content-Type' => 'text/html; charset=UTF-8'], 'Page not found.');
    }
}
