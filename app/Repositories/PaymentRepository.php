<?php

namespace Helpyard\App\Repositories;

use Helpyard\App\Core\PaymentException;
use Helpyard\App\Services\SSLCommerzGateway;
use PDO;
use RuntimeException;

class PaymentRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function prepareAttempt(int $orderId, int $userId): array
    {
        $this->connection->beginTransaction();
        try {
            $orderQuery = $this->connection->prepare(
                'SELECT o.id, o.order_number, o.status, o.final_total, o.reservation_expires_at, '
                . 'o.shipping_full_name, o.shipping_phone, o.shipping_address_line_1, o.shipping_city, '
                . 'o.shipping_country, o.shipping_postal_code, u.email '
                . 'FROM orders o JOIN users u ON u.id = o.user_id '
                . 'WHERE o.id = :order_id AND o.user_id = :user_id AND u.role = :role FOR UPDATE'
            );
            $orderQuery->execute(['order_id' => $orderId, 'user_id' => $userId, 'role' => 'customer']);
            $order = $orderQuery->fetch();
            if ($order === false || $order['status'] !== 'payment_pending'
                || $order['reservation_expires_at'] === null
                || strtotime($order['reservation_expires_at'] . ' UTC') <= time()
            ) {
                throw new PaymentException('This order is not available for payment. Please review its current status.');
            }

            $activePayment = $this->connection->prepare(
                "SELECT id, transaction_id, session_key, gateway_url, created_at FROM payment_transactions "
                . "WHERE order_id = :order_id AND status = 'pending' ORDER BY id DESC LIMIT 1 FOR UPDATE"
            );
            $activePayment->execute(['order_id' => $orderId]);
            $existing = $activePayment->fetch();
            if ($existing !== false) {
                if (is_string($existing['gateway_url']) && $existing['gateway_url'] !== '') {
                    $this->connection->commit();
                    return [
                        'transaction_id' => $existing['transaction_id'],
                        'redirect_url' => $existing['gateway_url'],
                    ];
                }
                throw new PaymentException('A payment session is already being prepared. Please try again shortly.');
            }

            $staleAttempt = $this->connection->prepare(
                "SELECT id FROM payment_transactions "
                . "WHERE order_id = :order_id AND status = 'initiating' "
                . 'AND created_at < CURRENT_TIMESTAMP - INTERVAL 2 MINUTE '
                . 'ORDER BY id DESC LIMIT 1 FOR UPDATE'
            );
            $staleAttempt->execute(['order_id' => $orderId]);
            $initiating = $staleAttempt->fetch();
            if ($initiating !== false) {
                $expireAttempt = $this->connection->prepare(
                    "UPDATE payment_transactions SET status = 'failed' WHERE id = :id AND status = 'initiating'"
                );
                $expireAttempt->execute(['id' => $initiating['id']]);
            }

            $itemsQuery = $this->connection->prepare(
                'SELECT product_name, quantity FROM order_items WHERE order_id = :order_id ORDER BY id ASC'
            );
            $itemsQuery->execute(['order_id' => $orderId]);
            $items = $itemsQuery->fetchAll();
            if ($items === []) {
                throw new PaymentException('This order has no items to pay for.');
            }

            $transactionId = 'HY' . strtoupper(bin2hex(random_bytes(12)));
            $insert = $this->connection->prepare(
                "INSERT INTO payment_transactions (order_id, provider, transaction_id, amount, currency, status) "
                . "VALUES (:order_id, 'sslcommerz', :transaction_id, :amount, 'BDT', 'initiating')"
            );
            $insert->execute([
                'order_id' => $orderId,
                'transaction_id' => $transactionId,
                'amount' => $order['final_total'],
            ]);
            $this->connection->commit();

            return [
                'transaction_id' => $transactionId,
                'order_number' => $order['order_number'],
                'amount' => $order['final_total'],
                'customer_email' => $order['email'],
                'address' => [
                    'full_name' => $order['shipping_full_name'],
                    'phone' => $order['shipping_phone'],
                    'address_line_1' => $order['shipping_address_line_1'],
                    'city' => $order['shipping_city'],
                    'country' => $order['shipping_country'],
                    'postal_code' => $order['shipping_postal_code'],
                ],
                'items' => $items,
            ];
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function saveGatewaySession(string $transactionId, string $sessionKey, string $redirectUrl): void
    {
        $statement = $this->connection->prepare(
            "UPDATE payment_transactions SET session_key = :session_key, gateway_url = :gateway_url, status = 'pending' "
            . "WHERE transaction_id = :transaction_id AND status = 'initiating'"
        );
        $statement->execute([
            'session_key' => $sessionKey,
            'gateway_url' => $redirectUrl,
            'transaction_id' => $transactionId,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Could not save the payment provider session.');
        }
    }

    public function findOrderIdForTransaction(string $transactionId, int $userId): ?int
    {
        $statement = $this->connection->prepare(
            'SELECT o.id FROM payment_transactions pt JOIN orders o ON o.id = pt.order_id '
            . 'WHERE pt.transaction_id = :transaction_id AND o.user_id = :user_id LIMIT 1'
        );
        $statement->execute(['transaction_id' => $transactionId, 'user_id' => $userId]);
        $orderId = $statement->fetchColumn();

        return $orderId === false ? null : (int) $orderId;
    }

    public function markInitiationFailed(string $transactionId): void
    {
        $statement = $this->connection->prepare(
            "UPDATE payment_transactions SET status = 'failed' "
            . "WHERE transaction_id = :transaction_id AND status = 'initiating'"
        );
        $statement->execute(['transaction_id' => $transactionId]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Could not record the failed payment attempt.');
        }
    }

    public function confirmValidatedPayment(array $validation): array
    {
        $transactionId = $validation['tran_id'] ?? null;
        $validationId = $validation['val_id'] ?? null;
        $amount = SSLCommerzGateway::normalizedAmount($validation['amount'] ?? null);
        $currency = strtoupper((string) ($validation['currency_type'] ?? $validation['currency'] ?? ''));
        if (!is_string($transactionId) || !preg_match('/^HY[A-F0-9]{24}$/', $transactionId)
            || !is_string($validationId) || $validationId === '' || strlen($validationId) > 100
            || $amount === null || $currency !== 'BDT'
            || !in_array(strtoupper((string) ($validation['status'] ?? '')), ['VALID', 'VALIDATED'], true)
        ) {
            throw new PaymentException('The verified payment data is incomplete or invalid.');
        }

        $this->connection->beginTransaction();
        try {
            $orderLookup = $this->connection->prepare(
                'SELECT order_id FROM payment_transactions '
                . 'WHERE transaction_id = :transaction_id AND provider = :provider LIMIT 1'
            );
            $orderLookup->execute(['transaction_id' => $transactionId, 'provider' => 'sslcommerz']);
            $orderId = $orderLookup->fetchColumn();
            if ($orderId === false) {
                throw new PaymentException('The payment does not match a pending order.');
            }

            $orderQuery = $this->connection->prepare(
                'SELECT status, reservation_expires_at, final_total FROM orders WHERE id = :order_id FOR UPDATE'
            );
            $orderQuery->execute(['order_id' => $orderId]);
            $order = $orderQuery->fetch();
            if ($order === false) {
                throw new PaymentException('The payment order could not be found.');
            }

            $paymentQuery = $this->connection->prepare(
                'SELECT id, order_id, amount, currency, status AS payment_status, validation_id '
                . 'FROM payment_transactions WHERE transaction_id = :transaction_id '
                . 'AND provider = :provider AND order_id = :order_id FOR UPDATE'
            );
            $paymentQuery->execute([
                'transaction_id' => $transactionId,
                'provider' => 'sslcommerz',
                'order_id' => $orderId,
            ]);
            $payment = $paymentQuery->fetch();
            if ($payment === false || $payment['currency'] !== $currency
                || SSLCommerzGateway::normalizedAmount($payment['amount']) !== $amount
                || SSLCommerzGateway::normalizedAmount($order['final_total']) !== $amount
            ) {
                throw new PaymentException('The payment does not match a pending order.');
            }

            if (in_array($payment['payment_status'], ['paid', 'review_required'], true)) {
                if ($payment['validation_id'] !== $validationId) {
                    throw new PaymentException('A different validation reference was already recorded.');
                }
                $this->connection->commit();
                return ['order_id' => (int) $payment['order_id'], 'already_processed' => true];
            }
            if (!in_array($payment['payment_status'], ['pending', 'initiating', 'failed', 'cancelled'], true)) {
                throw new PaymentException('This payment attempt is no longer active.');
            }

            $reservationActive = $order['status'] === 'payment_pending'
                && $order['reservation_expires_at'] !== null
                && strtotime($order['reservation_expires_at'] . ' UTC') > time();
            $riskLevel = (string) ($validation['risk_level'] ?? '0');
            $riskCleared = $riskLevel === '0';
            if ($reservationActive && $riskCleared) {
                $updateOrder = $this->connection->prepare(
                    "UPDATE orders SET status = 'paid' WHERE id = :order_id AND status = 'payment_pending'"
                );
                $updateOrder->execute(['order_id' => $payment['order_id']]);
                if ($updateOrder->rowCount() !== 1) {
                    throw new RuntimeException('Could not update the order after payment confirmation.');
                }
                $paymentStatus = 'paid';
            } else {
                if ($order['status'] === 'payment_pending') {
                    $this->restoreReservedInventory((int) $payment['order_id']);
                }
                $updateOrder = $this->connection->prepare(
                    "UPDATE orders SET status = 'payment_review' "
                    . "WHERE id = :order_id AND status IN ('payment_pending', 'cancelled', 'paid')"
                );
                $updateOrder->execute(['order_id' => $payment['order_id']]);
                if ($updateOrder->rowCount() !== 1 && $order['status'] !== 'payment_review') {
                    error_log(
                        'Verified payment requires manual reconciliation for order '
                        . $payment['order_id'] . ' in state ' . $order['status']
                    );
                }
                $paymentStatus = 'review_required';
            }

            $updatePayment = $this->connection->prepare(
                'UPDATE payment_transactions SET status = :status, validation_id = :validation_id WHERE id = :id'
            );
            $updatePayment->execute([
                'status' => $paymentStatus,
                'validation_id' => $validationId,
                'id' => $payment['id'],
            ]);
            $this->connection->commit();

            return ['order_id' => (int) $payment['order_id'], 'already_processed' => false];
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    private function restoreReservedInventory(int $orderId): void
    {
        $items = $this->connection->prepare(
            'SELECT product_id, product_variant_id, quantity FROM order_items WHERE order_id = :order_id FOR UPDATE'
        );
        $items->execute(['order_id' => $orderId]);
        $restoreProduct = $this->connection->prepare(
            'UPDATE products SET stock_quantity = stock_quantity + :quantity WHERE id = :product_id'
        );
        $restoreVariant = $this->connection->prepare(
            'UPDATE product_variants SET stock_quantity = stock_quantity + :quantity '
            . 'WHERE id = :variant_id AND product_id = :product_id'
        );

        foreach ($items->fetchAll() as $item) {
            if ($item['product_variant_id'] === null) {
                $restoreProduct->execute([
                    'quantity' => $item['quantity'],
                    'product_id' => $item['product_id'],
                ]);
                if ($restoreProduct->rowCount() !== 1) {
                    throw new RuntimeException('Could not release stock for a payment-review order.');
                }
            } else {
                $restoreVariant->execute([
                    'quantity' => $item['quantity'],
                    'variant_id' => $item['product_variant_id'],
                    'product_id' => $item['product_id'],
                ]);
                if ($restoreVariant->rowCount() !== 1) {
                    error_log(
                        'Payment review references a missing product option: order '
                        . $orderId . ', variant ' . $item['product_variant_id']
                    );
                }
            }
        }
    }
}
