<?php

namespace Helpyard\App\Controllers;

use Helpyard\App\Core\Database;
use Helpyard\App\Repositories\CatalogRepository;
use Helpyard\App\Core\Response;
use Helpyard\App\Core\SessionSecurity;
use RuntimeException;
use Throwable;

class StorefrontController
{
    public function __construct(private array $databaseConfig, private array $appConfig)
    {
    }

    public function index(array $params = []): Response
    {
        return $this->render('home.php', [
            'title' => 'Useful digital products. One trusted place.',
            'description' => 'Explore practical courses, websites, software, books, and security devices from Helpyard.store.',
            'canonicalPath' => '/',
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
            'canonicalPath' => '/' . $section,
        ]);
    }

    public function allProducts(array $params = []): Response
    {
        return $this->render('catalog.php', [
            'title' => 'All products',
            'description' => 'Browse courses, ready websites, software, books, and security devices together.',
            'type' => '',
            'category' => '',
            'canonicalPath' => '/products',
        ]);
    }

    public function product(array $params = []): Response
    {
        $slug = $params['slug'] ?? '';
        if (!is_string($slug) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return $this->notFound();
        }

        try {
            $product = (new CatalogRepository(Database::connect($this->databaseConfig)))
                ->findActiveProductBySlug($slug);
        } catch (Throwable $exception) {
            error_log('Storefront product request failed: ' . $exception->getMessage());

            return new Response(503, ['Content-Type' => 'text/html; charset=UTF-8'], 'Product details are temporarily unavailable.');
        }
        if ($product === null) {
            return $this->notFound();
        }
        $product['images'] = array_values(array_filter(
            $product['images'],
            fn (array $image): bool => $this->safeImageSource((string) ($image['image_url'] ?? ''))
        ));

        $descriptionSource = trim(strip_tags((string) ($product['short_description'] ?: $product['description'] ?: '')));
        $descriptionSource = preg_replace('/\s+/u', ' ', $descriptionSource) ?? $descriptionSource;
        if (preg_match('/^.{0,157}/us', $descriptionSource, $descriptionMatch) === 1) {
            $description = trim($descriptionMatch[0]);
            if (preg_match('/^.{158}/us', $descriptionSource) === 1) {
                $description .= '…';
            }
        } else {
            $description = 'View product details, options, and availability from Helpyard.store.';
        }
        if ($description === '') {
            $description = 'View product details, options, and availability from Helpyard.store.';
        }

        $baseUrl = rtrim((string) ($this->appConfig['base_url'] ?? 'http://localhost:8000'), '/');
        $productUrl = $baseUrl . '/product/' . rawurlencode($slug);
        $imageUrls = [];
        foreach ($product['images'] as $image) {
            $imageUrl = (string) $image['image_url'];
            if (str_starts_with($imageUrl, '/')) {
                $imageUrls[] = $baseUrl . $imageUrl;
            } elseif (filter_var($imageUrl, FILTER_VALIDATE_URL) !== false
                && in_array(parse_url($imageUrl, PHP_URL_SCHEME), ['http', 'https'], true)
            ) {
                $imageUrls[] = $imageUrl;
            }
        }

        if ($product['variants'] !== []) {
            $prices = [];
            $hasAvailableStock = false;
            foreach ($product['variants'] as $variant) {
                $prices[] = (float) ($variant['price_override'] ?? $product['price']);
                if ((int) $variant['stock_quantity'] > 0) {
                    $hasAvailableStock = true;
                    break;
                }
            }
            $offer = [
                '@type' => 'AggregateOffer',
                'priceCurrency' => 'BDT',
                'lowPrice' => number_format(min($prices), 2, '.', ''),
                'highPrice' => number_format(max($prices), 2, '.', ''),
                'offerCount' => count($prices),
                'availability' => $hasAvailableStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'url' => $productUrl,
            ];
        } else {
            $hasAvailableStock = (int) $product['stock_quantity'] > 0;
            $offer = [
                '@type' => 'Offer',
                'priceCurrency' => 'BDT',
                'price' => (string) $product['price'],
                'availability' => $hasAvailableStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'url' => $productUrl,
            ];
        }
        $productStructuredData = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product['name'],
            'description' => strip_tags((string) ($product['description'] ?: $product['short_description'] ?: $product['name'])),
            'image' => $imageUrls,
            'offers' => $offer,
        ];

        return $this->render('product.php', [
            'product' => $product,
            'title' => $product['name'],
            'description' => $description,
            'canonicalPath' => '/product/' . rawurlencode($slug),
            'canonicalUrl' => $productUrl,
            'productStructuredData' => $productStructuredData,
            'hasAvailableStock' => $hasAvailableStock,
        ]);
    }

    private function render(string $template, array $data): Response
    {
        $path = __DIR__ . '/../Views/' . $template;
        if (!is_file($path)) {
            throw new RuntimeException('Storefront template is missing: ' . $template);
        }

        $data['csrfToken'] = SessionSecurity::csrfToken();
        if (isset($data['canonicalPath'])) {
            $data['canonicalUrl'] = rtrim((string) ($this->appConfig['base_url'] ?? 'http://localhost:8000'), '/')
                . $data['canonicalPath'];
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

    private function safeImageSource(string $value): bool
    {
        if ($value === '' || preg_match('/[\x00-\x20]/', $value) === 1 || str_contains($value, '\\')) {
            return false;
        }
        if (str_starts_with($value, '/') && !str_starts_with($value, '//')) {
            return true;
        }

        $parts = parse_url($value);

        return is_array($parts)
            && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            && isset($parts['host']);
    }
}
