<?php

namespace Helpyard\App\Repositories;

use Helpyard\App\Core\AdminCatalogException;
use PDO;

class AdminCatalogRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function dashboard(): array
    {
        $categories = $this->connection->query(
            'SELECT c.id, c.name, c.slug, c.is_active, '
            . '(SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.is_active = 1) AS active_product_count '
            . 'FROM categories c ORDER BY c.name, c.id'
        )->fetchAll();
        $activeCategories = $this->connection->query(
            'SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name, id'
        )->fetchAll();
        $products = $this->connection->query(
            'SELECT p.id, p.name, p.slug, p.product_type, p.short_description, p.description, '
            . 'p.price, p.compare_price, p.stock_quantity, p.category_id, p.is_active, '
            . 'c.name AS category_name, c.is_active AS category_is_active, '
            . '(SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id = p.id AND pv.is_active = 1) AS active_variant_count '
            . 'FROM products p LEFT JOIN categories c ON c.id = p.category_id '
            . 'ORDER BY p.updated_at DESC, p.id DESC LIMIT 200'
        )->fetchAll();
        $variants = $this->connection->query(
            'SELECT pv.id, pv.product_id, pv.name, pv.sku, pv.price_override, pv.stock_quantity, pv.is_active '
            . 'FROM product_variants pv JOIN products p ON p.id = pv.product_id '
            . 'ORDER BY p.name, pv.name, pv.id'
        )->fetchAll();
        $variantsByProduct = [];
        foreach ($variants as $variant) {
            $variantsByProduct[(int) $variant['product_id']][] = $variant;
        }
        foreach ($products as &$product) {
            $product['variants'] = $variantsByProduct[(int) $product['id']] ?? [];
        }
        unset($product);

        return ['categories' => $categories, 'active_categories' => $activeCategories, 'products' => $products];
    }

    public function createCategory(array $category, int $adminId): void
    {
        $this->connection->beginTransaction();
        try {
            $this->assertCategorySlugAvailable($category['slug'], null);
            $insert = $this->connection->prepare(
                'INSERT INTO categories (name, slug, is_active) VALUES (:name, :slug, 1)'
            );
            $insert->execute(['name' => $category['name'], 'slug' => $category['slug']]);
            $categoryId = (int) $this->connection->lastInsertId();
            $this->audit($adminId, 'category.created', 'category', $categoryId, $category);
            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function updateCategory(int $categoryId, array $category, bool $active, int $adminId): bool
    {
        $this->connection->beginTransaction();
        try {
            $lookup = $this->connection->prepare(
                'SELECT id, name, slug, is_active FROM categories WHERE id = :category_id FOR UPDATE'
            );
            $lookup->execute(['category_id' => $categoryId]);
            $before = $lookup->fetch();
            if ($before === false) {
                $this->connection->rollBack();
                return false;
            }
            if (!$active && (int) $before['is_active'] === 1) {
                $products = $this->connection->prepare(
                    'SELECT COUNT(*) FROM products WHERE category_id = :category_id AND is_active = 1'
                );
                $products->execute(['category_id' => $categoryId]);
                if ((int) $products->fetchColumn() > 0) {
                    throw new AdminCatalogException('Deactivate or move this category’s active products before hiding it.');
                }
            }

            $this->assertCategorySlugAvailable($category['slug'], $categoryId);
            $update = $this->connection->prepare(
                'UPDATE categories SET name = :name, slug = :slug, is_active = :is_active WHERE id = :category_id'
            );
            $update->execute([
                'name' => $category['name'],
                'slug' => $category['slug'],
                'is_active' => $active ? 1 : 0,
                'category_id' => $categoryId,
            ]);
            $this->audit($adminId, 'category.updated', 'category', $categoryId, [
                'before' => $before,
                'after' => [...$category, 'is_active' => $active ? 1 : 0],
            ]);
            $this->connection->commit();

            return true;
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function createProduct(array $product, int $adminId): int
    {
        $this->connection->beginTransaction();
        try {
            $this->assertProductSlugAvailable($product['slug'], null);
            $this->assertCategoryUsable($product['category_id'], $product['is_active']);
            $insert = $this->connection->prepare(
                'INSERT INTO products '
                . '(category_id, product_type, name, slug, short_description, description, price, compare_price, stock_quantity, is_active) '
                . 'VALUES (:category_id, :product_type, :name, :slug, :short_description, :description, '
                . ':price, :compare_price, :stock_quantity, :is_active)'
            );
            $insert->execute($product);
            $productId = (int) $this->connection->lastInsertId();
            $this->audit($adminId, 'product.created', 'product', $productId, $this->productAuditData($product));
            $this->connection->commit();

            return $productId;
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function updateProduct(int $productId, array $product, int $adminId): bool
    {
        $this->connection->beginTransaction();
        try {
            $lookup = $this->connection->prepare(
                'SELECT p.id, p.category_id, p.product_type, p.name, p.slug, p.price, p.compare_price, '
                . 'p.stock_quantity, p.is_active, '
                . '(SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id = p.id AND pv.is_active = 1) '
                . 'AS active_variant_count FROM products p WHERE p.id = :product_id FOR UPDATE'
            );
            $lookup->execute(['product_id' => $productId]);
            $before = $lookup->fetch();
            if ($before === false) {
                $this->connection->rollBack();
                return false;
            }
            if ($product['product_type'] !== $before['product_type']) {
                throw new AdminCatalogException('A product type cannot be changed after creation.');
            }

            $this->assertProductSlugAvailable($product['slug'], $productId);
            $this->assertCategoryUsable($product['category_id'], $product['is_active']);
            $product['product_type'] = $before['product_type'];
            if ((int) $before['active_variant_count'] > 0) {
                $product['stock_quantity'] = (int) $before['stock_quantity'];
            }
            $this->assertAvailableStockDoesNotOverflow($productId, null, $product['stock_quantity']);
            $update = $this->connection->prepare(
                'UPDATE products SET category_id = :category_id, name = :name, slug = :slug, '
                . 'short_description = :short_description, description = :description, '
                . 'price = :price, compare_price = :compare_price, stock_quantity = :stock_quantity, '
                . 'is_active = :is_active WHERE id = :product_id'
            );
            $update->execute([
                'category_id' => $product['category_id'],
                'name' => $product['name'],
                'slug' => $product['slug'],
                'short_description' => $product['short_description'],
                'description' => $product['description'],
                'price' => $product['price'],
                'compare_price' => $product['compare_price'],
                'stock_quantity' => $product['stock_quantity'],
                'is_active' => $product['is_active'],
                'product_id' => $productId,
            ]);
            $this->audit($adminId, 'product.updated', 'product', $productId, [
                'before' => $before,
                'after' => $this->productAuditData($product),
            ]);
            $this->connection->commit();

            return true;
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function updateVariantStock(int $variantId, int $stockQuantity, int $adminId): bool
    {
        $this->connection->beginTransaction();
        try {
            $lookup = $this->connection->prepare(
                'SELECT pv.product_id, pv.name, pv.sku, pv.stock_quantity, p.name AS product_name '
                . 'FROM product_variants pv JOIN products p ON p.id = pv.product_id '
                . 'WHERE pv.id = :variant_id FOR UPDATE'
            );
            $lookup->execute(['variant_id' => $variantId]);
            $before = $lookup->fetch();
            if ($before === false) {
                $this->connection->rollBack();
                return false;
            }
            $this->assertAvailableStockDoesNotOverflow(
                (int) $before['product_id'],
                $variantId,
                $stockQuantity
            );

            $update = $this->connection->prepare(
                'UPDATE product_variants SET stock_quantity = :stock_quantity WHERE id = :variant_id'
            );
            $update->execute(['stock_quantity' => $stockQuantity, 'variant_id' => $variantId]);
            $this->audit($adminId, 'product_variant.stock_updated', 'product_variant', $variantId, [
                'product_id' => (int) $before['product_id'],
                'product_name' => $before['product_name'],
                'variant_name' => $before['name'],
                'sku' => $before['sku'],
                'before_stock' => (int) $before['stock_quantity'],
                'after_stock' => $stockQuantity,
            ]);
            $this->connection->commit();

            return true;
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    private function assertCategorySlugAvailable(string $slug, ?int $exceptId): void
    {
        $statement = $this->connection->prepare(
            'SELECT id FROM categories WHERE slug = :slug' . ($exceptId === null ? '' : ' AND id <> :except_id') . ' FOR UPDATE'
        );
        $parameters = ['slug' => $slug];
        if ($exceptId !== null) {
            $parameters['except_id'] = $exceptId;
        }
        $statement->execute($parameters);
        if ($statement->fetchColumn() !== false) {
            throw new AdminCatalogException('That category URL slug is already in use.');
        }
    }

    private function assertProductSlugAvailable(string $slug, ?int $exceptId): void
    {
        $statement = $this->connection->prepare(
            'SELECT id FROM products WHERE slug = :slug' . ($exceptId === null ? '' : ' AND id <> :except_id') . ' FOR UPDATE'
        );
        $parameters = ['slug' => $slug];
        if ($exceptId !== null) {
            $parameters['except_id'] = $exceptId;
        }
        $statement->execute($parameters);
        if ($statement->fetchColumn() !== false) {
            throw new AdminCatalogException('That product URL slug is already in use.');
        }
    }

    private function assertCategoryUsable(?int $categoryId, int $productActive): void
    {
        if ($categoryId === null) {
            return;
        }
        $category = $this->connection->prepare(
            'SELECT is_active FROM categories WHERE id = :category_id FOR UPDATE'
        );
        $category->execute(['category_id' => $categoryId]);
        $active = $category->fetchColumn();
        if ($active === false || ($productActive === 1 && (int) $active !== 1)) {
            throw new AdminCatalogException('Choose an active category for an active product.');
        }
    }

    private function assertAvailableStockDoesNotOverflow(int $productId, ?int $variantId, int $stockQuantity): void
    {
        if ($variantId === null) {
            $reserved = $this->connection->prepare(
                'SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi '
                . 'JOIN orders o ON o.id = oi.order_id '
                . 'WHERE oi.product_id = :product_id AND oi.product_variant_id IS NULL '
                . "AND o.status = 'payment_pending' AND o.reservation_expires_at > UTC_TIMESTAMP()"
            );
            $reserved->execute(['product_id' => $productId]);
        } else {
            $reserved = $this->connection->prepare(
                'SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi '
                . 'JOIN orders o ON o.id = oi.order_id '
                . 'WHERE oi.product_id = :product_id AND oi.product_variant_id = :variant_id '
                . "AND o.status = 'payment_pending' AND o.reservation_expires_at > UTC_TIMESTAMP()"
            );
            $reserved->execute(['product_id' => $productId, 'variant_id' => $variantId]);
        }
        $reservedQuantity = (int) $reserved->fetchColumn();
        if ($stockQuantity > 2147483647 - $reservedQuantity) {
            throw new AdminCatalogException(
                'Available stock plus active checkout reservations cannot exceed 2,147,483,647.'
            );
        }
    }

    private function audit(int $adminId, string $action, string $subjectType, int $subjectId, array $details): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO admin_audit_logs (actor_user_id, action, subject_type, subject_id, details) '
            . 'VALUES (:actor_user_id, :action, :subject_type, :subject_id, :details)'
        );
        $statement->execute([
            'actor_user_id' => $adminId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'details' => json_encode($details, JSON_THROW_ON_ERROR),
        ]);
    }

    private function productAuditData(array $product): array
    {
        return [
            'category_id' => $product['category_id'],
            'product_type' => $product['product_type'],
            'name' => $product['name'],
            'slug' => $product['slug'],
            'price' => $product['price'],
            'compare_price' => $product['compare_price'],
            'stock_quantity' => $product['stock_quantity'],
            'is_active' => $product['is_active'],
        ];
    }

    private function rollback(): void
    {
        if ($this->connection->inTransaction()) {
            $this->connection->rollBack();
        }
    }
}
