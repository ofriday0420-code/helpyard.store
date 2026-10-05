<?php

use Helpyard\App\Core\CheckoutException;
use Helpyard\App\Core\Database;
use Helpyard\App\Repositories\OrderRepository;

$input = json_decode((string) stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
foreach (['session_id', 'user_id', 'address_id', 'barrier_file', 'ready_file'] as $requiredKey) {
    if (!isset($input[$requiredKey])) {
        fwrite(STDERR, 'Missing worker input: ' . $requiredKey);
        exit(2);
    }
}

file_put_contents($input['ready_file'], 'ready', LOCK_EX);
$deadline = microtime(true) + 10;
while (!is_file($input['barrier_file']) && microtime(true) < $deadline) {
    usleep(10000);
}
if (!is_file($input['barrier_file'])) {
    fwrite(STDERR, 'Timed out waiting for the checkout start barrier.');
    exit(2);
}

try {
    $config = require __DIR__ . '/../bootstrap.php';
    $connection = Database::connect($config['database']);
    $order = (new OrderRepository($connection))->createFromCart(
        (string) $input['session_id'],
        (int) $input['user_id'],
        (int) $input['address_id']
    );
    fwrite(STDOUT, 'success:' . $order['order_number']);
    exit(0);
} catch (CheckoutException) {
    fwrite(STDOUT, 'rejected');
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class . ': ' . $exception->getMessage());
    exit(1);
}
