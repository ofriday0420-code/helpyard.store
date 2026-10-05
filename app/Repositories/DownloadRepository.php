<?php

namespace Helpyard\App\Repositories;

use PDO;

class DownloadRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function forCustomer(int $userId): array
    {
        $statement = $this->connection->prepare(
            'SELECT de.id AS entitlement_id, pf.download_name, oi.product_name, o.order_number, '
            . 'de.granted_at FROM download_entitlements de '
            . 'JOIN product_files pf ON pf.id = de.product_file_id '
            . 'JOIN order_items oi ON oi.id = de.order_item_id '
            . 'JOIN orders o ON o.id = oi.order_id '
            . 'WHERE o.user_id = :user_id AND de.revoked_at IS NULL AND pf.is_active = 1 '
            . "AND o.status IN ('paid', 'processing', 'shipped', 'delivered', 'digital_ready', 'completed') "
            . 'ORDER BY de.granted_at DESC, de.id DESC'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }

    public function findForCustomer(int $entitlementId, int $userId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT pf.storage_key, pf.download_name FROM download_entitlements de '
            . 'JOIN product_files pf ON pf.id = de.product_file_id '
            . 'JOIN order_items oi ON oi.id = de.order_item_id '
            . 'JOIN orders o ON o.id = oi.order_id '
            . 'WHERE de.id = :entitlement_id AND o.user_id = :user_id '
            . 'AND de.revoked_at IS NULL AND pf.is_active = 1 '
            . "AND o.status IN ('paid', 'processing', 'shipped', 'delivered', 'digital_ready', 'completed') "
            . 'LIMIT 1'
        );
        $statement->execute(['entitlement_id' => $entitlementId, 'user_id' => $userId]);
        $download = $statement->fetch();

        return $download === false ? null : $download;
    }
}
