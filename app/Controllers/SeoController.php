<?php

namespace Helpyard\App\Controllers;

use Helpyard\App\Core\Database;
use Helpyard\App\Core\Response;
use Helpyard\App\Repositories\CatalogRepository;
use RuntimeException;
use Throwable;

class SeoController
{
    private const INDEXABLE_PATHS = [
        '/',
        '/products',
        '/courses',
        '/websites',
        '/software',
        '/books',
        '/devices',
    ];

    public function __construct(private array $databaseConfig, private array $appConfig)
    {
    }

    public function sitemap(array $params = []): Response
    {
        try {
            $baseUrl = $this->baseUrl();
            $products = (new CatalogRepository(Database::connect($this->databaseConfig)))->sitemapProducts();
        } catch (Throwable $exception) {
            error_log('Sitemap request failed: ' . $exception->getMessage());

            return new Response(503, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Sitemap is temporarily unavailable.');
        }

        $entries = [];
        foreach (self::INDEXABLE_PATHS as $path) {
            $entries[] = ['location' => $baseUrl . $path, 'lastmod' => null];
        }
        foreach ($products as $product) {
            $lastModified = null;
            if (is_string($product['updated_at'] ?? null) && $product['updated_at'] !== '') {
                try {
                    $lastModified = (new \DateTimeImmutable($product['updated_at'], new \DateTimeZone('UTC')))
                        ->format('Y-m-d');
                } catch (\Throwable) {
                    $lastModified = null;
                }
            }
            $entries[] = [
                'location' => $baseUrl . '/product/' . rawurlencode((string) $product['slug']),
                'lastmod' => $lastModified,
            ];
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($entries as $entry) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . htmlspecialchars($entry['location'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
            if ($entry['lastmod'] !== null) {
                $xml .= '    <lastmod>' . htmlspecialchars($entry['lastmod'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</lastmod>\n";
            }
            $xml .= "  </url>\n";
        }
        $xml .= "</urlset>\n";

        return new Response(200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ], $xml);
    }

    public function robots(array $params = []): Response
    {
        try {
            $sitemapUrl = $this->baseUrl() . '/sitemap.xml';
        } catch (RuntimeException $exception) {
            error_log('Robots policy could not be rendered: ' . $exception->getMessage());

            return new Response(503, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Robots policy is temporarily unavailable.');
        }

        $body = "User-agent: *\n"
            . "Allow: /\n"
            . "Disallow: /account\n"
            . "Disallow: /admin\n"
            . "Disallow: /cart\n"
            . "Disallow: /checkout\n"
            . "Disallow: /login\n"
            . "Disallow: /register\n"
            . "Disallow: /orders\n"
            . "Disallow: /payments\n"
            . "Sitemap: {$sitemapUrl}\n";

        return new Response(200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ], $body);
    }

    private function baseUrl(): string
    {
        $configured = trim((string) ($this->appConfig['base_url'] ?? ''));
        $parts = parse_url($configured);
        if (!is_array($parts) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || !isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])
        ) {
            throw new RuntimeException('APP_URL must be an absolute HTTP or HTTPS base URL.');
        }

        $origin = strtolower($parts['scheme']) . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $origin .= ':' . (int) $parts['port'];
        }

        return rtrim($origin . (string) ($parts['path'] ?? ''), '/');
    }
}
