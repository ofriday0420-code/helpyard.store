<?php

namespace Helpyard\App\Repositories;

use PDO;

class CatalogRepository
{
    private const PRODUCT_TYPES = ['physical', 'digital', 'software', 'course', 'book', 'website'];

    public function __construct(private PDO $connection)
    {
    }

    public function findActiveProducts(array $filters, int $limit, int $offset): array
    {
        $conditions = ['p.is_active = 1', '(p.category_id IS NULL OR c.is_active = 1)'];
        $parameters = [];

        if (isset($filters['category'])) {
            $conditions[] = 'c.slug = :category';
            $parameters['category'] = $filters['category'];
        }

        if (isset($filters['product_type'])) {
            $conditions[] = 'p.product_type = :product_type';
            $parameters['product_type'] = $filters['product_type'];
        }

        if (isset($filters['search'])) {
            $conditions[] = "(p.name LIKE :search_name ESCAPE '!' "
                . "OR p.short_description LIKE :search_description ESCAPE '!' "
                . "OR p.description LIKE :search_long_description ESCAPE '!' "
                . "OR c.name LIKE :search_category ESCAPE '!')";
            $search = self::escapeSearchTerm($filters['search']);
            $parameters['search_name'] = '%' . $search . '%';
            $parameters['search_description'] = '%' . $search . '%';
            $parameters['search_long_description'] = '%' . $search . '%';
            $parameters['search_category'] = '%' . $search . '%';
        }

        $where = implode(' AND ', $conditions);
        $count = $this->connection->prepare(
            'SELECT COUNT(*) FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE ' . $where
        );
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();

        $products = $this->connection->prepare(
            'SELECT p.id, p.name, p.slug, p.product_type, p.short_description, p.price, p.compare_price, '
            . 'p.stock_quantity, EXISTS(SELECT 1 FROM product_variants pv '
            . 'WHERE pv.product_id = p.id AND pv.is_active = 1) AS has_variants, '
            . 'c.name AS category_name, c.slug AS category_slug '
            . 'FROM products p LEFT JOIN categories c ON c.id = p.category_id '
            . 'WHERE ' . $where . ' ORDER BY p.id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $name => $value) {
            $products->bindValue(':' . $name, $value, PDO::PARAM_STR);
        }
        $products->bindValue(':limit', $limit, PDO::PARAM_INT);
        $products->bindValue(':offset', $offset, PDO::PARAM_INT);
        $products->execute();

        return [
            'products' => $products->fetchAll(),
            'total' => $total,
        ];
    }

    public function findActiveCategories(): array
    {
        $query = $this->connection->query(
            'SELECT c.id, c.name, c.slug, COUNT(p.id) AS product_count '
            . 'FROM categories c LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1 '
            . 'WHERE c.is_active = 1 '
            . 'GROUP BY c.id, c.name, c.slug ORDER BY c.name ASC'
        );

        return $query->fetchAll();
    }

    public function sitemapProducts(): array
    {
        return $this->connection->query(
            'SELECT p.slug, p.updated_at FROM products p '
            . 'LEFT JOIN categories c ON c.id = p.category_id '
            . 'WHERE p.is_active = 1 AND (p.category_id IS NULL OR c.is_active = 1) '
            . 'ORDER BY p.id DESC'
        )->fetchAll();
    }

    public function findActiveProductBySlug(string $slug): ?array
    {
        $productQuery = $this->connection->prepare(
            'SELECT p.id, p.name, p.slug, p.product_type, p.short_description, p.description, '
            . 'p.price, p.compare_price, p.stock_quantity, c.name AS category_name, c.slug AS category_slug '
            . 'FROM products p LEFT JOIN categories c ON c.id = p.category_id '
            . 'WHERE p.slug = :slug AND p.is_active = 1 AND (p.category_id IS NULL OR c.is_active = 1) LIMIT 1'
        );
        $productQuery->execute(['slug' => $slug]);
        $product = $productQuery->fetch();

        if ($product === false) {
            return null;
        }

        $imageQuery = $this->connection->prepare(
            'SELECT id, image_url, alt_text, sort_order FROM product_images '
            . 'WHERE product_id = :product_id ORDER BY sort_order ASC, id ASC'
        );
        $imageQuery->execute(['product_id' => $product['id']]);
        $product['images'] = $imageQuery->fetchAll();

        $variantQuery = $this->connection->prepare(
            'SELECT id, name, sku, price_override, stock_quantity, attributes '
            . 'FROM product_variants WHERE product_id = :product_id AND is_active = 1 ORDER BY id ASC'
        );
        $variantQuery->execute(['product_id' => $product['id']]);
        $product['variants'] = array_map(
            static function (array $variant): array {
                $variant['attributes'] = json_decode($variant['attributes'], true, 512, JSON_THROW_ON_ERROR);
                return $variant;
            },
            $variantQuery->fetchAll()
        );

        return $product;
    }

    public static function productTypes(): array
    {
        return self::PRODUCT_TYPES;
    }

    public static function escapeSearchTerm(string $term): string
    {
        return strtr($term, ['!' => '!!', '%' => '!%', '_' => '!_']);
    }
}
