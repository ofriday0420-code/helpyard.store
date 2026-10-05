<?php

use Helpyard\App\Core\Database;
use Helpyard\App\Repositories\OrderRepository;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$config = require __DIR__ . '/../bootstrap.php';

try {
    $orders = new OrderRepository(Database::connect($config['database']));
    $released = 0;
    do {
        $batch = $orders->releaseExpiredReservations(100);
        $released += $batch;
    } while ($batch === 100);

    echo 'Released expired reservations and cancelled ' . $released . " order(s).\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'Reservation cleanup failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
