<?php

namespace Helpyard\App\Repositories;

use Helpyard\App\Core\AdminFileException;
use PDO;

class AdminFileRepository
{
    private const DOWNLOADABLE_TYPES = ['digital', 'software', 'course', 'book', 'website'];

    public function __construct(private PDO $connection)
    {
    }

    public function productsWithFiles(): array
    {
        $typeList = $this->downloadableTypeList();
        $products = $this->connection->query(
            'SELECT p.id, p.name, p.product_type, p.slug FROM products p '
            . 'WHERE p.product_type IN (' . $typeList . ') ORDER BY p.name, p.id'
        )->fetchAll();
        if ($products === []) {
            return [];
        }

        $files = $this->connection->query(
            'SELECT pf.id, pf.product_id, pf.download_name, pf.is_active, pf.created_at '
            . 'FROM product_files pf ORDER BY pf.product_id, pf.id DESC'
        )->fetchAll();
        $filesByProduct = [];
        foreach ($files as $file) {
            $filesByProduct[(int) $file['product_id']][] = $file;
        }
        foreach ($products as &$product) {
            $product['files'] = $filesByProduct[(int) $product['id']] ?? [];
        }
        unset($product);

        return $products;
    }

    public function addFile(
        int $productId,
        int $adminId,
        string $storageKey,
        string $downloadName,
        int $fileSize,
        string $mimeType
    ): void {
        $this->connection->beginTransaction();
        try {
            $product = $this->connection->prepare(
                'SELECT id FROM products WHERE id = :product_id AND product_type IN ('
                . $this->downloadableTypeList() . ') FOR UPDATE'
            );
            $product->execute(['product_id' => $productId]);
            if ($product->fetchColumn() === false) {
                throw new AdminFileException('Choose an existing digital product, book, course, or website.');
            }

            $file = $this->connection->prepare(
                'INSERT INTO product_files (product_id, storage_key, download_name) '
                . 'VALUES (:product_id, :storage_key, :download_name)'
            );
            $file->execute([
                'product_id' => $productId,
                'storage_key' => $storageKey,
                'download_name' => $downloadName,
            ]);
            $fileId = (int) $this->connection->lastInsertId();

            $entitlements = $this->connection->prepare(
                'INSERT INTO download_entitlements (order_item_id, product_file_id) '
                . 'SELECT oi.id, :file_id FROM order_items oi '
                . 'JOIN orders o ON o.id = oi.order_id '
                . "WHERE oi.product_id = :product_id AND oi.product_type IN ('digital', 'software', 'book', 'course', 'website') "
                . "AND o.status IN ('paid', 'processing', 'shipped', 'delivered', 'digital_ready', 'completed') "
                . 'ON DUPLICATE KEY UPDATE revoked_at = NULL'
            );
            $entitlements->execute(['file_id' => $fileId, 'product_id' => $productId]);

            $this->audit(
                $adminId,
                'product_file.uploaded',
                $fileId,
                [
                    'product_id' => $productId,
                    'download_name' => $downloadName,
                    'file_size' => $fileSize,
                    'mime_type' => $mimeType,
                ]
            );
            $this->connection->commit();
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function revokeFile(int $fileId, int $adminId): bool
    {
        $this->connection->beginTransaction();
        try {
            $file = $this->connection->prepare(
                'SELECT product_id, download_name FROM product_files '
                . 'WHERE id = :file_id AND is_active = 1 FOR UPDATE'
            );
            $file->execute(['file_id' => $fileId]);
            $record = $file->fetch();
            if ($record === false) {
                $this->connection->rollBack();
                return false;
            }

            $deactivate = $this->connection->prepare(
                'UPDATE product_files SET is_active = 0 WHERE id = :file_id AND is_active = 1'
            );
            $deactivate->execute(['file_id' => $fileId]);
            if ($deactivate->rowCount() !== 1) {
                throw new AdminFileException('The private file changed while it was being revoked.');
            }
            $revokeEntitlements = $this->connection->prepare(
                'UPDATE download_entitlements SET revoked_at = UTC_TIMESTAMP() '
                . 'WHERE product_file_id = :file_id AND revoked_at IS NULL'
            );
            $revokeEntitlements->execute(['file_id' => $fileId]);

            $this->audit(
                $adminId,
                'product_file.revoked',
                $fileId,
                [
                    'product_id' => (int) $record['product_id'],
                    'download_name' => $record['download_name'],
                    'revoked_entitlements' => $revokeEntitlements->rowCount(),
                ]
            );
            $this->connection->commit();

            return true;
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    private function audit(int $adminId, string $action, int $subjectId, array $details): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO admin_audit_logs (actor_user_id, action, subject_type, subject_id, details) '
            . 'VALUES (:actor_user_id, :action, :subject_type, :subject_id, :details)'
        );
        $statement->execute([
            'actor_user_id' => $adminId,
            'action' => $action,
            'subject_type' => 'product_file',
            'subject_id' => $subjectId,
            'details' => json_encode($details, JSON_THROW_ON_ERROR),
        ]);
    }

    private function downloadableTypeList(): string
    {
        return implode(', ', array_map(
            fn (string $type): string => $this->connection->quote($type),
            self::DOWNLOADABLE_TYPES
        ));
    }
}
