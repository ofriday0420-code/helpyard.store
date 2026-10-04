<?php

namespace Helpyard\App\Controllers;

use Helpyard\App\Core\Database;
use Helpyard\App\Core\Request;
use Helpyard\App\Core\Response;
use Helpyard\App\Repositories\CatalogRepository;
use PDOException;
use RuntimeException;

class CatalogController
{
    public function __construct(private array $databaseConfig)
    {
    }

    public function index(array $params, Request $request): Response
    {
        $query = $request->query();
        $filters = [];

        if (isset($query['category']) && $query['category'] !== '') {
            if (!is_string($query['category']) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $query['category'])) {
                return $this->badRequest('Invalid category filter.');
            }
            $filters['category'] = $query['category'];
        }

        if (isset($query['type']) && $query['type'] !== '') {
            if (!is_string($query['type']) || !in_array($query['type'], CatalogRepository::productTypes(), true)) {
                return $this->badRequest('Invalid product type filter.');
            }
            $filters['product_type'] = $query['type'];
        }

        $page = $this->positiveInteger($query['page'] ?? 1, 1);
        $perPage = min($this->positiveInteger($query['per_page'] ?? 20, 20), 50);

        try {
            $repository = new CatalogRepository(Database::connect($this->databaseConfig));
            $result = $repository->findActiveProducts($filters, $perPage, ($page - 1) * $perPage);
        } catch (PDOException | RuntimeException $exception) {
            return $this->databaseUnavailable($exception);
        }

        return new Response(200, ['Content-Type' => 'application/json; charset=UTF-8'], [
            'success' => true,
            'count' => count($result['products']),
            'products' => $result['products'],
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $result['total'],
                'last_page' => max(1, (int) ceil($result['total'] / $perPage)),
            ],
        ]);
    }

    public function categories(array $params, Request $request): Response
    {
        try {
            $categories = (new CatalogRepository(Database::connect($this->databaseConfig)))->findActiveCategories();
        } catch (PDOException | RuntimeException $exception) {
            return $this->databaseUnavailable($exception);
        }

        return new Response(200, ['Content-Type' => 'application/json; charset=UTF-8'], [
            'success' => true,
            'categories' => $categories,
        ]);
    }

    public function show(array $params, Request $request): Response
    {
        $slug = $params['slug'] ?? null;
        if (!is_string($slug) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return new Response(404, ['Content-Type' => 'application/json; charset=UTF-8'], [
                'success' => false,
                'error' => 'Product not found.',
            ]);
        }

        try {
            $product = (new CatalogRepository(Database::connect($this->databaseConfig)))->findActiveProductBySlug($slug);
        } catch (PDOException | RuntimeException $exception) {
            return $this->databaseUnavailable($exception);
        }

        if ($product === null) {
            return new Response(404, ['Content-Type' => 'application/json; charset=UTF-8'], [
                'success' => false,
                'error' => 'Product not found.',
            ]);
        }

        return new Response(200, ['Content-Type' => 'application/json; charset=UTF-8'], [
            'success' => true,
            'product' => $product,
        ]);
    }

    private function positiveInteger(mixed $value, int $default): int
    {
        if (!is_string($value) && !is_int($value)) {
            return $default;
        }

        $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $validated === false ? $default : $validated;
    }

    private function badRequest(string $message): Response
    {
        return new Response(400, ['Content-Type' => 'application/json; charset=UTF-8'], [
            'success' => false,
            'error' => $message,
        ]);
    }

    private function databaseUnavailable(PDOException | RuntimeException $exception): Response
    {
        error_log('Catalog database request failed: ' . $exception->getMessage());

        return new Response(503, ['Content-Type' => 'application/json; charset=UTF-8'], [
            'success' => false,
            'error' => 'Product catalog is temporarily unavailable.',
        ]);
    }
}
