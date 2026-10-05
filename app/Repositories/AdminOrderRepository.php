<?php

namespace Helpyard\App\Repositories;

use PDO;

class AdminOrderRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function queue(int $limit = 100): array
    {
        $statement = $this->connection->prepare(
            'SELECT o.id, o.order_number, o.status, o.final_total, o.created_at, '
            . 'u.name AS customer_name, u.email AS customer_email, '
            . 'pt.provider, pt.transaction_id, pt.validation_id, pt.status AS payment_status, '
            . 'pt.amount AS payment_amount, pt.currency AS payment_currency '
            . 'FROM orders o JOIN users u ON u.id = o.user_id '
            . 'LEFT JOIN payment_transactions pt ON pt.id = ('
            . 'SELECT MAX(latest.id) FROM payment_transactions latest WHERE latest.order_id = o.id) '
            . "ORDER BY CASE WHEN o.status = 'payment_review' THEN 0 ELSE 1 END, o.id DESC LIMIT :limit"
        );
        $statement->bindValue(':limit', max(1, min($limit, 100)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function find(int $orderId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT o.id, o.user_id, o.order_number, o.status, o.subtotal, o.shipping_total, '
            . 'o.discount_total, o.final_total, o.created_at, o.reservation_expires_at, '
            . 'o.shipping_full_name, o.shipping_phone, o.shipping_address_line_1, '
            . 'o.shipping_address_line_2, o.shipping_city, o.shipping_postal_code, o.shipping_country, '
            . 'u.name AS customer_name, u.email AS customer_email '
            . 'FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = :order_id LIMIT 1'
        );
        $statement->execute(['order_id' => $orderId]);
        $order = $statement->fetch();
        if ($order === false) {
            return null;
        }

        $items = $this->connection->prepare(
            'SELECT product_name, product_type, variant_name, quantity, unit_price, '
            . 'quantity * unit_price AS line_total FROM order_items '
            . 'WHERE order_id = :order_id ORDER BY id ASC'
        );
        $items->execute(['order_id' => $orderId]);
        $order['items'] = $items->fetchAll();

        $payments = $this->connection->prepare(
            'SELECT provider, transaction_id, validation_id, amount, currency, status, created_at, updated_at '
            . 'FROM payment_transactions WHERE order_id = :order_id ORDER BY id DESC'
        );
        $payments->execute(['order_id' => $orderId]);
        $order['payments'] = $payments->fetchAll();

        $shipment = $this->connection->prepare(
            'SELECT carrier, tracking_number, status, shipped_at, delivered_at '
            . 'FROM shipments WHERE order_id = :order_id LIMIT 1'
        );
        $shipment->execute(['order_id' => $orderId]);
        $shipmentDetails = $shipment->fetch();
        $order['shipment'] = $shipmentDetails === false ? null : $shipmentDetails;

        $notes = $this->connection->prepare(
            'SELECT orn.note, orn.created_at, u.name AS author_name, u.email AS author_email '
            . 'FROM order_review_notes orn JOIN users u ON u.id = orn.author_user_id '
            . 'WHERE orn.order_id = :order_id ORDER BY orn.id DESC'
        );
        $notes->execute(['order_id' => $orderId]);
        $order['review_notes'] = $notes->fetchAll();

        return $order;
    }

    public function addReviewNote(int $orderId, int $adminId, string $note): bool
    {
        $this->connection->beginTransaction();
        try {
            $orderLookup = $this->connection->prepare('SELECT id FROM orders WHERE id = :order_id FOR UPDATE');
            $orderLookup->execute(['order_id' => $orderId]);
            if ($orderLookup->fetchColumn() === false) {
                $this->connection->rollBack();
                return false;
            }

            $insert = $this->connection->prepare(
                'INSERT INTO order_review_notes (order_id, author_user_id, note) '
                . 'VALUES (:order_id, :author_user_id, :note)'
            );
            $insert->execute([
                'order_id' => $orderId,
                'author_user_id' => $adminId,
                'note' => $note,
            ]);
            $noteId = (int) $this->connection->lastInsertId();

            $audit = $this->connection->prepare(
                'INSERT INTO admin_audit_logs (actor_user_id, action, subject_type, subject_id, details) '
                . 'VALUES (:actor_user_id, :action, :subject_type, :subject_id, :details)'
            );
            $audit->execute([
                'actor_user_id' => $adminId,
                'action' => 'order.review_note_added',
                'subject_type' => 'order',
                'subject_id' => $orderId,
                'details' => json_encode(['note_id' => $noteId], JSON_THROW_ON_ERROR),
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
}
