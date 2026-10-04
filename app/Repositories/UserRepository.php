<?php

namespace Helpyard\App\Repositories;

use PDO;

class UserRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function createCustomer(string $name, string $email, string $passwordHash): int
    {
        $statement = $this->connection->prepare(
            "INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :password_hash, 'customer')"
        );
        $statement->execute([
            'name' => $name,
            'email' => $email,
            'password_hash' => $passwordHash,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function findByEmail(string $email): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, name, email, password_hash, role, email_verified_at FROM users WHERE email = :email LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }

    public function findCustomerById(int $id): ?array
    {
        $statement = $this->connection->prepare(
            "SELECT id, name, email, role FROM users WHERE id = :id AND role = 'customer' LIMIT 1"
        );
        $statement->execute(['id' => $id]);
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }

    public function updateCustomerName(int $id, string $name): void
    {
        $statement = $this->connection->prepare(
            "UPDATE users SET name = :name WHERE id = :id AND role = 'customer'"
        );
        $statement->execute(['name' => $name, 'id' => $id]);
    }

    public function addressesForCustomer(int $userId): array
    {
        $statement = $this->connection->prepare(
            'SELECT id, full_name, phone, address_line_1, address_line_2, city, postal_code, country, is_default '
            . 'FROM user_addresses WHERE user_id = :user_id ORDER BY is_default DESC, id DESC'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }

    public function addAddress(int $userId, array $address, bool $isDefault): void
    {
        $this->connection->beginTransaction();
        try {
            $customer = $this->connection->prepare(
                "SELECT id FROM users WHERE id = :user_id AND role = 'customer' FOR UPDATE"
            );
            $customer->execute(['user_id' => $userId]);
            if ($customer->fetchColumn() === false) {
                throw new \RuntimeException('Customer account is no longer available.');
            }

            $count = $this->connection->prepare('SELECT COUNT(*) FROM user_addresses WHERE user_id = :user_id');
            $count->execute(['user_id' => $userId]);
            $makeDefault = $isDefault || (int) $count->fetchColumn() === 0;

            if ($makeDefault) {
                $clearDefault = $this->connection->prepare(
                    'UPDATE user_addresses SET is_default = 0 WHERE user_id = :user_id'
                );
                $clearDefault->execute(['user_id' => $userId]);
            }

            $statement = $this->connection->prepare(
                'INSERT INTO user_addresses '
                . '(user_id, full_name, phone, address_line_1, address_line_2, city, postal_code, country, is_default) '
                . 'VALUES (:user_id, :full_name, :phone, :address_line_1, :address_line_2, :city, :postal_code, :country, :is_default)'
            );
            $statement->execute([
                'user_id' => $userId,
                'full_name' => $address['full_name'],
                'phone' => $address['phone'],
                'address_line_1' => $address['address_line_1'],
                'address_line_2' => $address['address_line_2'],
                'city' => $address['city'],
                'postal_code' => $address['postal_code'],
                'country' => $address['country'],
                'is_default' => $makeDefault ? 1 : 0,
            ]);

            $this->connection->commit();
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function deleteAddress(int $userId, int $addressId): bool
    {
        $this->connection->beginTransaction();
        try {
            $customer = $this->connection->prepare(
                "SELECT id FROM users WHERE id = :user_id AND role = 'customer' FOR UPDATE"
            );
            $customer->execute(['user_id' => $userId]);
            if ($customer->fetchColumn() === false) {
                $this->connection->commit();
                return false;
            }

            $find = $this->connection->prepare(
                'SELECT id, is_default FROM user_addresses WHERE id = :id AND user_id = :user_id FOR UPDATE'
            );
            $find->execute(['id' => $addressId, 'user_id' => $userId]);
            $address = $find->fetch();
            if ($address === false) {
                $this->connection->commit();
                return false;
            }

            $delete = $this->connection->prepare(
                'DELETE FROM user_addresses WHERE id = :id AND user_id = :user_id'
            );
            $delete->execute(['id' => $addressId, 'user_id' => $userId]);

            if ((int) $address['is_default'] === 1) {
                $nextDefault = $this->connection->prepare(
                    'UPDATE user_addresses SET is_default = 1 '
                    . 'WHERE id = (SELECT next_address.id FROM '
                    . '(SELECT id FROM user_addresses WHERE user_id = :user_id ORDER BY id DESC LIMIT 1) AS next_address)'
                );
                $nextDefault->execute(['user_id' => $userId]);
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

    public function loginAttempts(string $identifierHash, string $since): int
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM auth_login_attempts WHERE identifier_hash = :identifier_hash AND attempted_at >= :since'
        );
        $statement->execute(['identifier_hash' => $identifierHash, 'since' => $since]);

        return (int) $statement->fetchColumn();
    }

    public function recordLoginAttempt(string $identifierHash): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO auth_login_attempts (identifier_hash) VALUES (:identifier_hash)'
        );
        $statement->execute(['identifier_hash' => $identifierHash]);
    }

    public function clearLoginAttempts(string $identifierHash): void
    {
        $statement = $this->connection->prepare(
            'DELETE FROM auth_login_attempts WHERE identifier_hash = :identifier_hash'
        );
        $statement->execute(['identifier_hash' => $identifierHash]);
    }

    public function deleteExpiredLoginAttempts(string $before): void
    {
        $statement = $this->connection->prepare('DELETE FROM auth_login_attempts WHERE attempted_at < :before');
        $statement->execute(['before' => $before]);
    }
}
