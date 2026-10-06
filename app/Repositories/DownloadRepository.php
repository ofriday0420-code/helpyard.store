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
        $this->connection->beginTransaction();
        try {
            $this->connection->exec(
                'DELETE FROM download_link_tokens WHERE expires_at <= UTC_TIMESTAMP() '
                . 'ORDER BY expires_at ASC LIMIT 200'
            );
            $statement = $this->connection->prepare(
                'SELECT de.id AS entitlement_id, pf.download_name, oi.product_name, o.order_number, '
                . 'de.granted_at, de.download_count, de.max_downloads FROM download_entitlements de '
                . 'JOIN product_files pf ON pf.id = de.product_file_id '
                . 'JOIN order_items oi ON oi.id = de.order_item_id '
                . 'JOIN orders o ON o.id = oi.order_id '
                . 'WHERE o.user_id = :user_id AND de.revoked_at IS NULL AND pf.is_active = 1 '
                . "AND o.status IN ('paid', 'processing', 'shipped', 'delivered', 'digital_ready', 'completed') "
                . 'ORDER BY de.granted_at DESC, de.id DESC'
            );
            $statement->execute(['user_id' => $userId]);
            $downloads = $statement->fetchAll();
            $insertToken = $this->connection->prepare(
                'INSERT INTO download_link_tokens (entitlement_id, token_hash, expires_at) '
                . 'VALUES (:entitlement_id, :token_hash, :expires_at)'
            );
            $activeTokens = $this->connection->prepare(
                'SELECT COUNT(*) FROM download_link_tokens '
                . 'WHERE entitlement_id = :entitlement_id AND expires_at > UTC_TIMESTAMP()'
            );
            $pruneOldestToken = $this->connection->prepare(
                'DELETE FROM download_link_tokens WHERE entitlement_id = :entitlement_id '
                . 'AND expires_at > UTC_TIMESTAMP() ORDER BY created_at ASC, id ASC LIMIT 1'
            );

            foreach ($downloads as &$download) {
                $download['download_token'] = null;
                $download['link_expires_at'] = null;
                if ((int) $download['download_count'] >= (int) $download['max_downloads']) {
                    continue;
                }

                $activeTokens->execute(['entitlement_id' => (int) $download['entitlement_id']]);
                if ((int) $activeTokens->fetchColumn() >= 10) {
                    $pruneOldestToken->execute(['entitlement_id' => (int) $download['entitlement_id']]);
                }

                $token = bin2hex(random_bytes(32));
                $expiresAt = time() + 86400;
                $insertToken->bindValue(':entitlement_id', (int) $download['entitlement_id'], \PDO::PARAM_INT);
                $insertToken->bindValue(':token_hash', hash('sha256', $token));
                $insertToken->bindValue(':expires_at', gmdate('Y-m-d H:i:s', $expiresAt));
                $insertToken->execute();
                $download['download_token'] = $token;
                $download['link_expires_at'] = gmdate(DATE_ATOM, $expiresAt);
            }
            unset($download);
            $this->connection->commit();

            return $downloads;
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function findForCustomer(int $entitlementId, int $userId, string $token): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT pf.storage_key, pf.download_name, de.download_count, de.max_downloads '
            . 'FROM download_entitlements de '
            . 'JOIN product_files pf ON pf.id = de.product_file_id '
            . 'JOIN order_items oi ON oi.id = de.order_item_id '
            . 'JOIN orders o ON o.id = oi.order_id '
            . 'JOIN download_link_tokens dlt ON dlt.entitlement_id = de.id '
            . 'WHERE de.id = :entitlement_id AND o.user_id = :user_id '
            . 'AND de.revoked_at IS NULL AND pf.is_active = 1 '
            . 'AND dlt.token_hash = :token_hash AND dlt.expires_at > UTC_TIMESTAMP() '
            . 'AND de.download_count < de.max_downloads '
            . "AND o.status IN ('paid', 'processing', 'shipped', 'delivered', 'digital_ready', 'completed') "
            . 'LIMIT 1'
        );
        $statement->bindValue(':entitlement_id', $entitlementId, \PDO::PARAM_INT);
        $statement->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $statement->bindValue(':token_hash', hash('sha256', $token));
        $statement->execute();
        $download = $statement->fetch();

        return $download === false ? null : $download;
    }

    public function consumeForCustomer(int $entitlementId, int $userId, string $token): bool
    {
        $this->connection->beginTransaction();
        try {
            $statement = $this->connection->prepare(
                'SELECT de.id FROM download_entitlements de '
                . 'JOIN product_files pf ON pf.id = de.product_file_id '
                . 'JOIN order_items oi ON oi.id = de.order_item_id '
                . 'JOIN orders o ON o.id = oi.order_id '
                . 'JOIN download_link_tokens dlt ON dlt.entitlement_id = de.id '
                . 'WHERE de.id = :entitlement_id AND o.user_id = :user_id '
                . 'AND de.revoked_at IS NULL AND pf.is_active = 1 '
                . 'AND dlt.token_hash = :token_hash AND dlt.expires_at > UTC_TIMESTAMP() '
                . 'AND de.download_count < de.max_downloads '
                . "AND o.status IN ('paid', 'processing', 'shipped', 'delivered', 'digital_ready', 'completed') "
                . 'LIMIT 1 FOR UPDATE'
            );
            $statement->bindValue(':entitlement_id', $entitlementId, \PDO::PARAM_INT);
            $statement->bindValue(':user_id', $userId, \PDO::PARAM_INT);
            $statement->bindValue(':token_hash', hash('sha256', $token));
            $statement->execute();
            if ($statement->fetchColumn() === false) {
                $this->connection->rollBack();
                return false;
            }

            $increment = $this->connection->prepare(
                'UPDATE download_entitlements SET download_count = download_count + 1 '
                . 'WHERE id = :entitlement_id AND download_count < max_downloads'
            );
            $increment->execute(['entitlement_id' => $entitlementId]);
            if ($increment->rowCount() !== 1) {
                $this->connection->rollBack();
                return false;
            }

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
