<?php

namespace Helpyard\App\Repositories;

use PDO;
use Helpyard\App\Core\CartException;
use RuntimeException;

class CartRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function addProduct(string $cartKey, int $productId, int $quantity, ?int $variantId = null): void
    {
        $this->connection->beginTransaction();
        try {
            $cartId = $this->findOrCreateCart($cartKey);
            $cart = $this->connection->prepare('SELECT id FROM carts WHERE id = :id FOR UPDATE');
            $cart->execute(['id' => $cartId]);
            if ($cart->fetchColumn() === false) {
                throw new RuntimeException('Could not load the active cart.');
            }

            $product = $this->connection->prepare(
                'SELECT price, stock_quantity FROM products WHERE id = :id AND is_active = 1 FOR UPDATE'
            );
            $product->execute(['id' => $productId]);
            $productDetails = $product->fetch();
            if ($productDetails === false) {
                throw new CartException('This product is no longer available.');
            }

            if ($variantId === null) {
                $variantCount = $this->connection->prepare(
                    'SELECT COUNT(*) FROM product_variants WHERE product_id = :product_id AND is_active = 1'
                );
                $variantCount->execute(['product_id' => $productId]);
                if ((int) $variantCount->fetchColumn() > 0) {
                    throw new CartException('Choose a product option before adding it to your cart.');
                }
                $price = $productDetails['price'];
                $availableQuantity = (int) $productDetails['stock_quantity'];
            } else {
                $variant = $this->connection->prepare(
                    'SELECT price_override, stock_quantity FROM product_variants '
                    . 'WHERE id = :variant_id AND product_id = :product_id AND is_active = 1 FOR UPDATE'
                );
                $variant->execute(['variant_id' => $variantId, 'product_id' => $productId]);
                $variantDetails = $variant->fetch();
                if ($variantDetails === false) {
                    throw new CartException('That product option is no longer available.');
                }
                $price = $variantDetails['price_override'] ?? $productDetails['price'];
                $availableQuantity = (int) $variantDetails['stock_quantity'];
            }

            $item = $this->connection->prepare(
                'SELECT id, quantity FROM cart_items '
                . 'WHERE cart_id = :cart_id AND product_id = :product_id AND product_variant_id <=> :variant_id FOR UPDATE'
            );
            $item->execute(['cart_id' => $cartId, 'product_id' => $productId, 'variant_id' => $variantId]);
            $existingItem = $item->fetch();
            $newQuantity = $quantity + ($existingItem === false ? 0 : (int) $existingItem['quantity']);
            if ($newQuantity > 99 || $newQuantity > $availableQuantity) {
                throw new CartException('The requested quantity is not currently available.');
            }

            if ($existingItem === false) {
                $insert = $this->connection->prepare(
                    'INSERT INTO cart_items (cart_id, product_id, product_variant_id, quantity, unit_price) '
                    . 'VALUES (:cart_id, :product_id, :variant_id, :quantity, :unit_price)'
                );
                $insert->execute([
                    'cart_id' => $cartId,
                    'product_id' => $productId,
                    'variant_id' => $variantId,
                    'quantity' => $newQuantity,
                    'unit_price' => $price,
                ]);
            } else {
                $update = $this->connection->prepare(
                    'UPDATE cart_items SET quantity = :quantity, unit_price = :unit_price WHERE id = :id'
                );
                $update->execute([
                    'quantity' => $newQuantity,
                    'unit_price' => $price,
                    'id' => $existingItem['id'],
                ]);
            }

            $this->connection->commit();
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function updateQuantity(string $cartKey, int $itemId, int $quantity): bool
    {
        $this->connection->beginTransaction();
        try {
            $cartId = $this->findCart($cartKey);
            if ($cartId === null) {
                $this->connection->commit();
                return false;
            }

            $statement = $this->connection->prepare(
                'SELECT ci.id, ci.product_variant_id, p.price, p.stock_quantity, p.is_active, '
                . 'pv.id AS active_variant_id, pv.price_override, pv.stock_quantity AS variant_stock '
                . 'FROM cart_items ci JOIN products p ON p.id = ci.product_id '
                . 'LEFT JOIN product_variants pv ON pv.id = ci.product_variant_id '
                . 'AND pv.product_id = p.id AND pv.is_active = 1 '
                . 'WHERE ci.id = :item_id AND ci.cart_id = :cart_id FOR UPDATE'
            );
            $statement->execute(['item_id' => $itemId, 'cart_id' => $cartId]);
            $item = $statement->fetch();
            if ($item === false || (int) $item['is_active'] !== 1
                || ($item['product_variant_id'] !== null && $item['active_variant_id'] === null)
                || $quantity > (int) ($item['product_variant_id'] === null ? $item['stock_quantity'] : $item['variant_stock'])
            ) {
                $this->connection->commit();
                return false;
            }

            $update = $this->connection->prepare(
                'UPDATE cart_items SET quantity = :quantity, unit_price = :unit_price WHERE id = :item_id'
            );
            $update->execute([
                'quantity' => $quantity,
                'unit_price' => $item['product_variant_id'] === null
                    ? $item['price']
                    : ($item['price_override'] ?? $item['price']),
                'item_id' => $itemId,
            ]);
            $this->connection->commit();

            return true;
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function removeItem(string $cartKey, int $itemId): bool
    {
        $cartId = $this->findCart($cartKey);
        if ($cartId === null) {
            return false;
        }

        $statement = $this->connection->prepare(
            'DELETE FROM cart_items WHERE id = :item_id AND cart_id = :cart_id'
        );
        $statement->execute(['item_id' => $itemId, 'cart_id' => $cartId]);

        return $statement->rowCount() > 0;
    }

    public function contents(string $cartKey): array
    {
        $cartId = $this->findCart($cartKey);
        if ($cartId === null) {
            return ['items' => [], 'subtotal' => '0.00'];
        }

        $statement = $this->connection->prepare(
            'SELECT ci.id AS cart_item_id, ci.quantity, p.id AS product_id, p.name, p.slug, '
            . 'p.product_type, p.is_active, ci.product_variant_id, pv.name AS variant_name, '
            . 'COALESCE(pv.price_override, p.price) AS current_price, '
            . 'ci.quantity * COALESCE(pv.price_override, p.price) AS line_total, '
            . 'COALESCE(pv.stock_quantity, p.stock_quantity) AS stock_quantity, '
            . 'CASE WHEN p.is_active = 1 AND (ci.product_variant_id IS NULL OR pv.id IS NOT NULL) THEN 1 ELSE 0 END AS is_available '
            . 'FROM cart_items ci JOIN products p ON p.id = ci.product_id '
            . 'LEFT JOIN product_variants pv ON pv.id = ci.product_variant_id '
            . 'AND pv.product_id = p.id AND pv.is_active = 1 '
            . 'WHERE ci.cart_id = :cart_id ORDER BY ci.id ASC'
        );
        $statement->execute(['cart_id' => $cartId]);
        $items = $statement->fetchAll();

        $subtotal = $this->connection->prepare(
            'SELECT COALESCE(SUM(ci.quantity * COALESCE(pv.price_override, p.price)), 0.00) '
            . 'FROM cart_items ci JOIN products p ON p.id = ci.product_id '
            . 'LEFT JOIN product_variants pv ON pv.id = ci.product_variant_id '
            . 'AND pv.product_id = p.id AND pv.is_active = 1 '
            . 'WHERE ci.cart_id = :cart_id AND p.is_active = 1 '
            . 'AND (ci.product_variant_id IS NULL OR pv.id IS NOT NULL)'
        );
        $subtotal->execute(['cart_id' => $cartId]);

        return ['items' => $items, 'subtotal' => $subtotal->fetchColumn()];
    }

    private function findOrCreateCart(string $cartKey): int
    {
        $insert = $this->connection->prepare(
            'INSERT INTO carts (session_id) VALUES (:session_id) '
            . 'ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
        );
        $insert->execute(['session_id' => $cartKey]);

        return (int) $this->connection->lastInsertId();
    }

    private function findCart(string $cartKey): ?int
    {
        $statement = $this->connection->prepare('SELECT id FROM carts WHERE session_id = :session_id LIMIT 1');
        $statement->execute(['session_id' => $cartKey]);
        $cartId = $statement->fetchColumn();

        return $cartId === false ? null : (int) $cartId;
    }
}
