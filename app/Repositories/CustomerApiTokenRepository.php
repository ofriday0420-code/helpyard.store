<?php

namespace Helpyard\App\Repositories;

use PDO;

class CustomerApiTokenRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function issue(int $userId, string $name): array
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = time() + 2_592_000;
        $this->connection->beginTransaction();
        try {
            $customer = $this->connection->prepare(
                "SELECT id FROM users WHERE id = :user_id AND role = 'customer' FOR UPDATE"
            );
            $customer->execute(['user_id' => $userId]);
            if ($customer->fetchColumn() === false) {
                $this->connection->rollBack();
                throw new \RuntimeException('Customer account is no longer available.');
            }

            $activeTokens = $this->connection->prepare(
                'SELECT id FROM customer_api_tokens WHERE user_id = :user_id '
                . 'AND revoked_at IS NULL AND expires_at > UTC_TIMESTAMP() '
                . 'ORDER BY created_at ASC, id ASC FOR UPDATE'
            );
            $activeTokens->execute(['user_id' => $userId]);
            $activeIds = $activeTokens->fetchAll(PDO::FETCH_COLUMN);
            $revokeIds = array_slice($activeIds, 0, max(0, count($activeIds) - 9));
            if ($revokeIds !== []) {
                $revokeOldest = $this->connection->prepare(
                    'UPDATE customer_api_tokens SET revoked_at = UTC_TIMESTAMP() '
                    . 'WHERE id = :token_id AND user_id = :user_id AND revoked_at IS NULL'
                );
                foreach ($revokeIds as $oldId) {
                    $revokeOldest->execute(['token_id' => $oldId, 'user_id' => $userId]);
                }
            }

            $insert = $this->connection->prepare(
                'INSERT INTO customer_api_tokens (user_id, name, token_hash, expires_at) '
                . 'VALUES (:user_id, :name, :token_hash, :expires_at)'
            );
            $insert->execute([
                'user_id' => $userId,
                'name' => $name,
                'token_hash' => hash('sha256', $token),
                'expires_at' => gmdate('Y-m-d H:i:s', $expiresAt),
            ]);
            $tokenId = (int) $this->connection->lastInsertId();

            $this->connection->commit();

            return [
                'id' => $tokenId,
                'token' => $token,
                'expires_at' => gmdate(DATE_ATOM, $expiresAt),
            ];
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function customerForToken(string $token): ?array
    {
        $this->connection->beginTransaction();
        try {
            $statement = $this->connection->prepare(
                "SELECT t.id AS token_id, u.id AS user_id, u.name, u.email "
                . 'FROM customer_api_tokens t JOIN users u ON u.id = t.user_id '
                . "WHERE t.token_hash = :token_hash AND t.revoked_at IS NULL "
                . "AND t.expires_at > UTC_TIMESTAMP() AND u.role = 'customer' LIMIT 1 FOR UPDATE"
            );
            $statement->execute(['token_hash' => hash('sha256', $token)]);
            $customer = $statement->fetch();
            if ($customer === false) {
                $this->connection->commit();
                return null;
            }

            $touch = $this->connection->prepare(
                'UPDATE customer_api_tokens SET last_used_at = UTC_TIMESTAMP() '
                . 'WHERE id = :token_id AND revoked_at IS NULL AND expires_at > UTC_TIMESTAMP()'
            );
            $touch->execute(['token_id' => $customer['token_id']]);
            $this->connection->commit();

            return $customer;
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function revoke(int $tokenId, int $userId): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE customer_api_tokens SET revoked_at = UTC_TIMESTAMP() '
            . 'WHERE id = :token_id AND user_id = :user_id AND revoked_at IS NULL'
        );
        $statement->execute(['token_id' => $tokenId, 'user_id' => $userId]);

        return $statement->rowCount() === 1;
    }

    public function forCustomer(int $userId): array
    {
        $statement = $this->connection->prepare(
            'SELECT id, name, created_at, expires_at, last_used_at '
            . 'FROM customer_api_tokens WHERE user_id = :user_id AND revoked_at IS NULL '
            . 'AND expires_at > UTC_TIMESTAMP() ORDER BY created_at DESC, id DESC LIMIT 100'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }
}
