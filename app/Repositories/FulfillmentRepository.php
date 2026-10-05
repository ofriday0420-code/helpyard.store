<?php

namespace Helpyard\App\Repositories;

use Helpyard\App\Core\FulfillmentException;
use Helpyard\App\Services\FulfillmentPolicy;
use PDO;

class FulfillmentRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function queue(): array
    {
        $statement = $this->connection->query(
            "SELECT o.id, o.order_number, o.status, o.final_total, o.created_at, "
            . 'o.shipping_full_name, o.shipping_city, '
            . '(SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count '
            . "FROM orders o WHERE o.status IN ('paid', 'processing', 'shipped') "
            . 'AND EXISTS (SELECT 1 FROM order_items eligible WHERE eligible.order_id = o.id) '
            . 'AND NOT EXISTS (SELECT 1 FROM order_items ineligible WHERE ineligible.order_id = o.id '
            . "AND ineligible.product_type NOT IN ('physical', 'book')) "
            . 'ORDER BY o.id ASC LIMIT 100'
        );

        return $statement->fetchAll();
    }

    public function advance(
        int $orderId,
        int $adminId,
        string $action,
        ?string $carrier = null,
        ?string $trackingNumber = null
    ): void {
        $this->connection->beginTransaction();
        try {
            $orderQuery = $this->connection->prepare(
                'SELECT id, status FROM orders WHERE id = :order_id FOR UPDATE'
            );
            $orderQuery->execute(['order_id' => $orderId]);
            $order = $orderQuery->fetch();
            if ($order === false) {
                throw new FulfillmentException('Order not found.');
            }

            $nextStatus = FulfillmentPolicy::nextStatus($order['status'], $action);
            if ($nextStatus === null) {
                throw new FulfillmentException('This order cannot make that fulfillment transition.');
            }

            $itemQuery = $this->connection->prepare(
                'SELECT product_type FROM order_items WHERE order_id = :order_id FOR UPDATE'
            );
            $itemQuery->execute(['order_id' => $orderId]);
            if (!FulfillmentPolicy::canShipProductTypes($itemQuery->fetchAll(PDO::FETCH_COLUMN))) {
                throw new FulfillmentException('This order contains items that do not use physical shipping.');
            }

            $updateOrder = $this->connection->prepare(
                'UPDATE orders SET status = :status WHERE id = :order_id AND status = :current_status'
            );
            $updateOrder->execute([
                'status' => $nextStatus,
                'order_id' => $orderId,
                'current_status' => $order['status'],
            ]);
            if ($updateOrder->rowCount() !== 1) {
                throw new FulfillmentException('The order status changed. Reload and try again.');
            }

            if ($action === 'mark_shipped') {
                if ($carrier === null || $trackingNumber === null) {
                    throw new FulfillmentException('Carrier and tracking number are required before shipping.');
                }
                $shipment = $this->connection->prepare(
                    "INSERT INTO shipments (order_id, carrier, tracking_number, status, shipped_at, created_by) "
                    . "VALUES (:order_id, :carrier, :tracking_number, 'shipped', UTC_TIMESTAMP(), :admin_id)"
                );
                $shipment->execute([
                    'order_id' => $orderId,
                    'carrier' => $carrier,
                    'tracking_number' => $trackingNumber,
                    'admin_id' => $adminId,
                ]);
            } elseif ($action === 'mark_delivered') {
                $shipment = $this->connection->prepare(
                    "UPDATE shipments SET status = 'delivered', delivered_at = UTC_TIMESTAMP() "
                    . "WHERE order_id = :order_id AND status = 'shipped'"
                );
                $shipment->execute(['order_id' => $orderId]);
                if ($shipment->rowCount() !== 1) {
                    throw new FulfillmentException('A shipped package record is required before delivery can be recorded.');
                }
            }

            $event = $this->connection->prepare(
                'INSERT INTO order_fulfillment_events (order_id, from_status, to_status, changed_by) '
                . 'VALUES (:order_id, :from_status, :to_status, :admin_id)'
            );
            $event->execute([
                'order_id' => $orderId,
                'from_status' => $order['status'],
                'to_status' => $nextStatus,
                'admin_id' => $adminId,
            ]);

            $this->connection->commit();
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }
}
