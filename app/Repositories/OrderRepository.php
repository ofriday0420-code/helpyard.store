<?php

namespace Helpyard\App\Repositories;

use Helpyard\App\Core\CheckoutException;
use PDO;
use RuntimeException;

class OrderRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function createFromCart(string $cartKey, int $userId, int $addressId): array
    {
        $this->releaseExpiredReservations();
        $this->connection->beginTransaction();
        try {
            $addressQuery = $this->connection->prepare(
                'SELECT full_name, phone, address_line_1, address_line_2, city, postal_code, country '
                . 'FROM user_addresses WHERE id = :address_id AND user_id = :user_id FOR UPDATE'
            );
            $addressQuery->execute(['address_id' => $addressId, 'user_id' => $userId]);
            $address = $addressQuery->fetch();
            if ($address === false) {
                throw new CheckoutException('Choose one of your saved addresses.');
            }

            $cartQuery = $this->connection->prepare(
                'SELECT id FROM carts WHERE session_id = :session_id FOR UPDATE'
            );
            $cartQuery->execute(['session_id' => $cartKey]);
            $cartId = $cartQuery->fetchColumn();
            if ($cartId === false) {
                throw new CheckoutException('Your cart is empty.');
            }

            $itemsQuery = $this->connection->prepare(
                'SELECT ci.id AS cart_item_id, ci.quantity, p.id AS product_id, p.name AS product_name, '
                . 'p.price AS product_price, p.stock_quantity AS product_stock, p.is_active AS product_active, '
                . 'pv.id AS variant_id, pv.name AS variant_name, pv.price_override, pv.stock_quantity AS variant_stock, '
                . 'pv.is_active AS variant_active '
                . 'FROM cart_items ci JOIN products p ON p.id = ci.product_id '
                . 'LEFT JOIN product_variants pv ON pv.id = ci.product_variant_id '
                . 'AND pv.product_id = p.id '
                . 'WHERE ci.cart_id = :cart_id ORDER BY ci.id ASC FOR UPDATE'
            );
            $itemsQuery->execute(['cart_id' => $cartId]);
            $items = $itemsQuery->fetchAll();
            if ($items === []) {
                throw new CheckoutException('Your cart is empty.');
            }

            $variantCount = $this->connection->prepare(
                'SELECT COUNT(*) FROM product_variants WHERE product_id = :product_id AND is_active = 1'
            );
            $decreaseProductStock = $this->connection->prepare(
                'UPDATE products SET stock_quantity = stock_quantity - :quantity '
                . 'WHERE id = :product_id AND is_active = 1 AND stock_quantity >= :available_quantity'
            );
            $decreaseVariantStock = $this->connection->prepare(
                'UPDATE product_variants SET stock_quantity = stock_quantity - :quantity '
                . 'WHERE id = :variant_id AND product_id = :product_id AND is_active = 1 '
                . 'AND stock_quantity >= :available_quantity'
            );

            $pricedItems = [];
            foreach ($items as $item) {
                $quantity = (int) $item['quantity'];
                if ((int) $item['product_active'] !== 1) {
                    throw new CheckoutException('A product in your cart is no longer available. Please update your cart.');
                }

                if ($item['variant_id'] !== null) {
                    if ((int) $item['variant_active'] !== 1 || (int) $item['variant_stock'] < $quantity) {
                        throw new CheckoutException('A product option in your cart no longer has enough stock.');
                    }
                    $price = $item['price_override'] ?? $item['product_price'];
                    $variantName = $item['variant_name'];
                    $decreaseVariantStock->execute([
                        'quantity' => $quantity,
                        'variant_id' => $item['variant_id'],
                        'product_id' => $item['product_id'],
                        'available_quantity' => $quantity,
                    ]);
                    if ($decreaseVariantStock->rowCount() !== 1) {
                        throw new CheckoutException('Stock changed while checking out. Please review your cart.');
                    }
                } else {
                    $variantCount->execute(['product_id' => $item['product_id']]);
                    if ((int) $variantCount->fetchColumn() > 0) {
                        throw new CheckoutException('Choose a product option before checking out.');
                    }
                    if ((int) $item['product_stock'] < $quantity) {
                        throw new CheckoutException('A product in your cart no longer has enough stock.');
                    }
                    $price = $item['product_price'];
                    $variantName = null;
                    $decreaseProductStock->execute([
                        'quantity' => $quantity,
                        'product_id' => $item['product_id'],
                        'available_quantity' => $quantity,
                    ]);
                    if ($decreaseProductStock->rowCount() !== 1) {
                        throw new CheckoutException('Stock changed while checking out. Please review your cart.');
                    }
                }

                $pricedItems[] = [
                    'product_id' => (int) $item['product_id'],
                    'product_name' => $item['product_name'],
                    'variant_id' => $item['variant_id'] === null ? null : (int) $item['variant_id'],
                    'variant_name' => $variantName,
                    'quantity' => $quantity,
                    'unit_price' => $price,
                ];
            }

            $subtotalExpressions = [];
            $subtotalParameters = [];
            foreach ($pricedItems as $index => $item) {
                $subtotalExpressions[] = '(CAST(:price_' . $index . ' AS DECIMAL(10,2)) * :quantity_' . $index . ')';
                $subtotalParameters['price_' . $index] = $item['unit_price'];
                $subtotalParameters['quantity_' . $index] = $item['quantity'];
            }
            $subtotalQuery = $this->connection->prepare('SELECT ' . implode(' + ', $subtotalExpressions));
            $subtotalQuery->execute($subtotalParameters);
            $subtotal = $subtotalQuery->fetchColumn();

            $orderNumber = 'HY-' . gmdate('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
            $orderQuery = $this->connection->prepare(
                "INSERT INTO orders (user_id, order_number, status, subtotal, final_total, "
                . 'shipping_full_name, shipping_phone, shipping_address_line_1, shipping_address_line_2, '
                . 'shipping_city, shipping_postal_code, shipping_country, reservation_expires_at) '
                . "VALUES (:user_id, :order_number, 'payment_pending', :subtotal, :final_total, "
                . ':full_name, :phone, :address_line_1, :address_line_2, :city, :postal_code, :country, '
                . 'DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 MINUTE))'
            );
            $orderQuery->execute([
                'user_id' => $userId,
                'order_number' => $orderNumber,
                'subtotal' => $subtotal,
                'final_total' => $subtotal,
                'full_name' => $address['full_name'],
                'phone' => $address['phone'],
                'address_line_1' => $address['address_line_1'],
                'address_line_2' => $address['address_line_2'],
                'city' => $address['city'],
                'postal_code' => $address['postal_code'],
                'country' => $address['country'],
            ]);
            $orderId = (int) $this->connection->lastInsertId();

            $insertItem = $this->connection->prepare(
                'INSERT INTO order_items (order_id, product_id, product_variant_id, product_name, variant_name, quantity, unit_price) '
                . 'VALUES (:order_id, :product_id, :variant_id, :product_name, :variant_name, :quantity, :unit_price)'
            );
            foreach ($pricedItems as $item) {
                $insertItem->execute([
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'],
                    'product_name' => $item['product_name'],
                    'variant_name' => $item['variant_name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                ]);
            }

            $clearCart = $this->connection->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id');
            $clearCart->execute(['cart_id' => $cartId]);
            $this->connection->commit();

            return ['id' => $orderId, 'order_number' => $orderNumber];
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function findForCustomer(int $orderId, int $userId): ?array
    {
        $orderQuery = $this->connection->prepare(
            'SELECT id, order_number, status, subtotal, final_total, created_at, reservation_expires_at, '
            . 'shipping_full_name, shipping_phone, shipping_address_line_1, shipping_address_line_2, '
            . 'shipping_city, shipping_postal_code, shipping_country '
            . 'FROM orders WHERE id = :id AND user_id = :user_id LIMIT 1'
        );
        $orderQuery->execute(['id' => $orderId, 'user_id' => $userId]);
        $order = $orderQuery->fetch();
        if ($order === false) {
            return null;
        }

        $itemsQuery = $this->connection->prepare(
            'SELECT product_name, variant_name, quantity, unit_price '
            . 'FROM order_items WHERE order_id = :order_id ORDER BY id ASC'
        );
        $itemsQuery->execute(['order_id' => $orderId]);
        $order['items'] = $itemsQuery->fetchAll();

        return $order;
    }

    public function releaseExpiredReservations(): int
    {
        $this->connection->beginTransaction();
        try {
            $ordersQuery = $this->connection->query(
                "SELECT id FROM orders WHERE status = 'payment_pending' "
                . 'AND reservation_expires_at IS NOT NULL AND reservation_expires_at <= UTC_TIMESTAMP() FOR UPDATE'
            );
            $orders = $ordersQuery->fetchAll(PDO::FETCH_COLUMN);
            if ($orders === []) {
                $this->connection->commit();
                return 0;
            }

            $itemsQuery = $this->connection->prepare(
                'SELECT product_id, product_variant_id, quantity FROM order_items WHERE order_id = :order_id FOR UPDATE'
            );
            $restoreProduct = $this->connection->prepare(
                'UPDATE products SET stock_quantity = stock_quantity + :quantity WHERE id = :product_id'
            );
            $restoreVariant = $this->connection->prepare(
                'UPDATE product_variants SET stock_quantity = stock_quantity + :quantity '
                . 'WHERE id = :variant_id AND product_id = :product_id'
            );
            $cancelOrder = $this->connection->prepare(
                "UPDATE orders SET status = 'cancelled' WHERE id = :order_id AND status = 'payment_pending'"
            );

            foreach ($orders as $orderId) {
                $itemsQuery->execute(['order_id' => $orderId]);
                foreach ($itemsQuery->fetchAll() as $item) {
                    if ($item['product_variant_id'] === null) {
                        $restoreProduct->execute([
                            'quantity' => $item['quantity'],
                            'product_id' => $item['product_id'],
                        ]);
                        if ($restoreProduct->rowCount() !== 1) {
                            throw new RuntimeException('Could not release an expired product reservation.');
                        }
                    } else {
                        $restoreVariant->execute([
                            'quantity' => $item['quantity'],
                            'variant_id' => $item['product_variant_id'],
                            'product_id' => $item['product_id'],
                        ]);
                        if ($restoreVariant->rowCount() !== 1) {
                            throw new RuntimeException('Could not release an expired product-option reservation.');
                        }
                    }
                }
                $cancelOrder->execute(['order_id' => $orderId]);
                if ($cancelOrder->rowCount() !== 1) {
                    throw new RuntimeException('Could not cancel an expired payment reservation.');
                }
            }

            $this->connection->commit();

            return count($orders);
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }
}
