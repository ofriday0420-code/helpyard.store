<?php

use Helpyard\App\Core\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$email = $argv[1] ?? '';
if ($argc !== 2 || !is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, 'Usage: php database/provision_admin.php <existing-customer-email>' . PHP_EOL);
    exit(2);
}

$config = require __DIR__ . '/../bootstrap.php';
$connection = null;

try {
    $connection = Database::connect($config['database']);
    $connection->beginTransaction();

    $lookup = $connection->prepare(
        'SELECT id, role FROM users WHERE email = :email LIMIT 1 FOR UPDATE'
    );
    $lookup->execute(['email' => $email]);
    $user = $lookup->fetch();
    if ($user === false || $user['role'] !== 'customer') {
        throw new RuntimeException('The email must belong to an existing customer account.');
    }

    $promote = $connection->prepare(
        "UPDATE users SET role = 'admin' WHERE id = :user_id AND role = 'customer'"
    );
    $promote->execute(['user_id' => $user['id']]);
    if ($promote->rowCount() !== 1) {
        throw new RuntimeException('The customer account could not be provisioned as an administrator.');
    }

    $audit = $connection->prepare(
        'INSERT INTO admin_audit_logs (actor_user_id, action, subject_type, subject_id, details) '
        . 'VALUES (:actor_user_id, :action, :subject_type, :subject_id, :details)'
    );
    $audit->execute([
        'actor_user_id' => $user['id'],
        'action' => 'user.admin_provisioned',
        'subject_type' => 'user',
        'subject_id' => $user['id'],
        'details' => json_encode([
            'previous_role' => 'customer',
            'new_role' => 'admin',
            'provisioned_via' => 'cli',
        ], JSON_THROW_ON_ERROR),
    ]);
    $connection->commit();
    fwrite(STDOUT, 'Administrator role provisioned for account ID ' . (int) $user['id'] . '.' . PHP_EOL);
} catch (Throwable $exception) {
    if ($connection instanceof PDO && $connection->inTransaction()) {
        $connection->rollBack();
    }
    fwrite(STDERR, 'Administrator provisioning failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
