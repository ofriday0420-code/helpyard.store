<?php

use Helpyard\App\Core\Database;
use Helpyard\App\Core\MigrationRunner;
use Helpyard\App\Core\CheckoutException;
use Helpyard\App\Core\PaymentException;
use Helpyard\App\Repositories\AdminFileRepository;
use Helpyard\App\Repositories\CatalogRepository;
use Helpyard\App\Repositories\CourseRepository;
use Helpyard\App\Repositories\DownloadRepository;
use Helpyard\App\Repositories\FulfillmentRepository;
use Helpyard\App\Repositories\OrderRepository;
use Helpyard\App\Repositories\PaymentRepository;

$config = require __DIR__ . '/../bootstrap.php';
if (!str_ends_with((string) $config['database']['database'], '_test')) {
    throw new RuntimeException('MySQL integration tests may only run against a database ending in _test.');
}

$connection = Database::connect($config['database']);
$runner = new MigrationRunner($connection, __DIR__ . '/../database/migrations');

if ($runner->migrate() !== []) {
    throw new RuntimeException('The second migration pass should not apply additional migrations.');
}

$migrationCount = (int) $connection->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
if ($migrationCount < 14) {
    throw new RuntimeException('The full migration set was not applied.');
}

$catalog = new CatalogRepository($connection);
$categories = $catalog->findActiveCategories();
$categorySlugs = array_column($categories, 'slug');
if (count($categories) < 5 || !in_array('books', $categorySlugs, true)) {
    throw new RuntimeException('The seeded active categories are unavailable through the catalog repository.');
}

$products = $catalog->findActiveProducts([], 50, 0);
if ($products['total'] < 5) {
    throw new RuntimeException('The seeded active products are unavailable through the catalog repository.');
}

if ($catalog->findActiveProductBySlug('business-website-starter') === null) {
    throw new RuntimeException('An active seeded product could not be loaded by its public slug.');
}

$connection->beginTransaction();
try {
    $hideCategory = $connection->prepare(
        'UPDATE categories SET is_active = 0 WHERE slug = :slug'
    );
    $hideCategory->execute(['slug' => 'books']);
    if ($hideCategory->rowCount() !== 1) {
        throw new RuntimeException('The seeded books category could not be hidden for the visibility check.');
    }

    $hiddenCategories = array_column($catalog->findActiveCategories(), 'slug');
    if (in_array('books', $hiddenCategories, true)
        || $catalog->findActiveProducts(['category' => 'books'], 50, 0)['total'] !== 0
        || $catalog->findActiveProductBySlug('modern-e-commerce-operations') !== null
    ) {
        throw new RuntimeException('Products in a hidden category are still exposed by the public catalog.');
    }
} finally {
    $connection->rollBack();
}

$fixture = strtoupper(bin2hex(random_bytes(6)));
$customerInsert = $connection->prepare(
    'INSERT INTO users (name, email, password_hash, role) '
    . 'VALUES (:name, :email, :password_hash, :role)'
);
$customerInsert->execute([
    'name' => 'CI Customer',
    'email' => 'ci-customer-' . strtolower($fixture) . '@example.invalid',
    'password_hash' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
    'role' => 'customer',
]);
$customerId = (int) $connection->lastInsertId();
$customerInsert->execute([
    'name' => 'CI Other Customer',
    'email' => 'ci-other-' . strtolower($fixture) . '@example.invalid',
    'password_hash' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
    'role' => 'customer',
]);
$otherCustomerId = (int) $connection->lastInsertId();
$customerInsert->execute([
    'name' => 'CI Administrator',
    'email' => 'ci-admin-' . strtolower($fixture) . '@example.invalid',
    'password_hash' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
    'role' => 'admin',
]);
$adminId = (int) $connection->lastInsertId();

$findProduct = $connection->prepare('SELECT id, price FROM products WHERE slug = :slug');
$findProduct->execute(['slug' => 'business-website-starter']);
$downloadProduct = $findProduct->fetch();
if ($downloadProduct === false) {
    throw new RuntimeException('The seeded website product is required for the payment integration check.');
}

$fileKey = 'ci/' . strtolower($fixture) . '.zip';
$fileInsert = $connection->prepare(
    'INSERT INTO product_files (product_id, storage_key, download_name) '
    . 'VALUES (:product_id, :storage_key, :download_name)'
);
$fileInsert->execute([
    'product_id' => $downloadProduct['id'],
    'storage_key' => $fileKey,
    'download_name' => 'ci-download.zip',
]);
$fileId = (int) $connection->lastInsertId();

$createOrder = static function (
    int $userId,
    string $status,
    string $orderNumber,
    array $product,
    string $productType
) use ($connection): int {
    $orderInsert = $connection->prepare(
        'INSERT INTO orders (user_id, order_number, status, subtotal, final_total, '
        . 'shipping_full_name, shipping_phone, shipping_address_line_1, shipping_city, shipping_country, '
        . 'reservation_expires_at) VALUES (:user_id, :order_number, :status, :subtotal, :final_total, '
        . "'CI Customer', '+8801000000000', 'CI test address', 'Dhaka', 'Bangladesh', "
        . 'DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 MINUTE))'
    );
    $orderInsert->execute([
        'user_id' => $userId,
        'order_number' => $orderNumber,
        'status' => $status,
        'subtotal' => $product['price'],
        'final_total' => $product['price'],
    ]);
    $orderId = (int) $connection->lastInsertId();
    $itemInsert = $connection->prepare(
        'INSERT INTO order_items '
        . '(order_id, product_id, product_variant_id, quantity, unit_price, product_name, product_type) '
        . 'VALUES (:order_id, :product_id, NULL, 1, :unit_price, :product_name, :product_type)'
    );
    $itemInsert->execute([
        'order_id' => $orderId,
        'product_id' => $product['id'],
        'unit_price' => $product['price'],
        'product_name' => 'CI integration item',
        'product_type' => $productType,
    ]);

    return $orderId;
};

$insertPayment = static function (int $orderId, string $transactionId, string $amount) use ($connection): void {
    $statement = $connection->prepare(
        "INSERT INTO payment_transactions (order_id, provider, transaction_id, amount, currency, status) "
        . "VALUES (:order_id, 'sslcommerz', :transaction_id, :amount, 'BDT', 'pending')"
    );
    $statement->execute([
        'order_id' => $orderId,
        'transaction_id' => $transactionId,
        'amount' => $amount,
    ]);
};

$paymentOrderId = $createOrder(
    $customerId,
    'payment_pending',
    'CI-' . $fixture . '-PAY',
    $downloadProduct,
    'website'
);
$downloadTransactionId = 'HY' . $fixture . strtoupper(bin2hex(random_bytes(6)));
$insertPayment($paymentOrderId, $downloadTransactionId, (string) $downloadProduct['price']);
$validatedPayment = [
    'tran_id' => $downloadTransactionId,
    'val_id' => 'CI-VALIDATION-' . $fixture,
    'amount' => (string) $downloadProduct['price'],
    'currency_type' => 'BDT',
    'status' => 'VALID',
    'risk_level' => '0',
];
$payments = new PaymentRepository($connection);
$confirmation = $payments->confirmValidatedPayment($validatedPayment);
$duplicateConfirmation = $payments->confirmValidatedPayment($validatedPayment);
if ($confirmation['order_id'] !== $paymentOrderId || $confirmation['already_processed']
    || $duplicateConfirmation['order_id'] !== $paymentOrderId || !$duplicateConfirmation['already_processed']
) {
    throw new RuntimeException('Validated payment confirmation is not safely idempotent.');
}
$orderStatus = $connection->prepare('SELECT status FROM orders WHERE id = :order_id');
$orderStatus->execute(['order_id' => $paymentOrderId]);
if ($orderStatus->fetchColumn() !== 'paid') {
    throw new RuntimeException('A verified low-risk payment did not mark its order paid.');
}

$downloads = new DownloadRepository($connection);
$entitlements = $downloads->forCustomer($customerId);
$entitlement = null;
foreach ($entitlements as $candidate) {
    if ($candidate['download_name'] === 'ci-download.zip') {
        $entitlement = $candidate;
        break;
    }
}
if ($entitlement === null
    || $downloads->findForCustomer((int) $entitlement['entitlement_id'], $otherCustomerId) !== null
) {
    throw new RuntimeException('Paid file access was not granted to its owner or leaked to another customer.');
}
if (!(new AdminFileRepository($connection))->revokeFile($fileId, $adminId)
    || $downloads->findForCustomer((int) $entitlement['entitlement_id'], $customerId) !== null
) {
    throw new RuntimeException('Revoking a private file did not revoke its customer entitlement.');
}

$courseProductQuery = $connection->prepare(
    'SELECT id, price FROM products WHERE slug = :slug AND product_type = :product_type'
);
$courseProductQuery->execute(['slug' => 'advanced-security-bootcamp', 'product_type' => 'course']);
$courseProduct = $courseProductQuery->fetch();
if ($courseProduct === false) {
    throw new RuntimeException('The seeded course product is required for the enrollment integration check.');
}
$courseQuery = $connection->prepare('SELECT id FROM courses WHERE product_id = :product_id');
$courseQuery->execute(['product_id' => $courseProduct['id']]);
$courseId = (int) $courseQuery->fetchColumn();
$positionQuery = $connection->prepare(
    'SELECT COALESCE(MAX(position), 0) + 1 FROM course_sections WHERE course_id = :course_id'
);
$positionQuery->execute(['course_id' => $courseId]);
$sectionInsert = $connection->prepare(
    'INSERT INTO course_sections (course_id, title, position) VALUES (:course_id, :title, :position)'
);
$sectionInsert->execute([
    'course_id' => $courseId,
    'title' => 'CI section ' . $fixture,
    'position' => (int) $positionQuery->fetchColumn(),
]);
$sectionId = (int) $connection->lastInsertId();
$lessonInsert = $connection->prepare(
    'INSERT INTO course_lessons (section_id, title, content, position, is_published) '
    . 'VALUES (:section_id, :title, :content, 1, 1)'
);
$lessonInsert->execute([
    'section_id' => $sectionId,
    'title' => 'CI lesson ' . $fixture,
    'content' => 'Integration-test lesson.',
]);
$lessonId = (int) $connection->lastInsertId();

$courseOrderId = $createOrder(
    $customerId,
    'payment_pending',
    'CI-' . $fixture . '-COURSE',
    $courseProduct,
    'course'
);
$courseTransactionId = 'HY' . strtoupper(bin2hex(random_bytes(12)));
$insertPayment($courseOrderId, $courseTransactionId, (string) $courseProduct['price']);
$courseValidation = $validatedPayment;
$courseValidation['tran_id'] = $courseTransactionId;
$courseValidation['val_id'] = 'CI-COURSE-VALIDATION-' . $fixture;
$courseValidation['amount'] = (string) $courseProduct['price'];
$payments->confirmValidatedPayment($courseValidation);

$courses = new CourseRepository($connection);
$courseAccess = $courses->findForCustomer($courseId, $customerId);
if ($courseAccess === null || $courses->findForCustomer($courseId, $otherCustomerId) !== null
    || !$courses->completeLesson($courseId, $lessonId, $customerId)
    || !$courses->completeLesson($courseId, $lessonId, $customerId)
) {
    throw new RuntimeException('Paid course access, customer isolation, or lesson progress is incorrect.');
}
$progress = $connection->prepare(
    'SELECT COUNT(*) FROM course_lesson_progress WHERE user_id = :user_id AND lesson_id = :lesson_id'
);
$progress->execute(['user_id' => $customerId, 'lesson_id' => $lessonId]);
if ((int) $progress->fetchColumn() !== 1) {
    throw new RuntimeException('Repeating lesson completion created duplicate progress records.');
}

$physicalQuery = $connection->prepare(
    'SELECT id, price FROM products WHERE slug = :slug AND product_type = :product_type'
);
$physicalQuery->execute(['slug' => 'security-device-kit', 'product_type' => 'physical']);
$physicalProduct = $physicalQuery->fetch();
if ($physicalProduct === false) {
    throw new RuntimeException('The seeded physical product is required for fulfillment integration checks.');
}
$shipmentOrderId = $createOrder(
    $customerId,
    'paid',
    'CI-' . $fixture . '-SHIP',
    $physicalProduct,
    'physical'
);
$fulfillment = new FulfillmentRepository($connection);
$fulfillment->advance($shipmentOrderId, $adminId, 'start_processing');
$fulfillment->advance($shipmentOrderId, $adminId, 'mark_shipped', 'CI Carrier', 'CI-' . $fixture);
$fulfillment->advance($shipmentOrderId, $adminId, 'mark_delivered');
$orderStatus->execute(['order_id' => $shipmentOrderId]);
if ($orderStatus->fetchColumn() !== 'delivered') {
    throw new RuntimeException('A physical order did not complete the shipment lifecycle.');
}
$shipment = $connection->prepare('SELECT status, tracking_number FROM shipments WHERE order_id = :order_id');
$shipment->execute(['order_id' => $shipmentOrderId]);
$shipmentDetails = $shipment->fetch();
if ($shipmentDetails === false || $shipmentDetails['status'] !== 'delivered'
    || $shipmentDetails['tracking_number'] !== 'CI-' . $fixture
) {
    throw new RuntimeException('Carrier, tracking, or delivery state was not persisted.');
}

$stockQuery = $connection->prepare('SELECT stock_quantity FROM products WHERE id = :product_id');
$stockQuery->execute(['product_id' => $physicalProduct['id']]);
$originalPhysicalStock = (int) $stockQuery->fetchColumn();
$stockUpdate = $connection->prepare('UPDATE products SET stock_quantity = 1 WHERE id = :product_id');
$stockUpdate->execute(['product_id' => $physicalProduct['id']]);

$addressInsert = $connection->prepare(
    'INSERT INTO user_addresses '
    . '(user_id, full_name, phone, address_line_1, city, country, is_default) '
    . 'VALUES (:user_id, :full_name, :phone, :address, :city, :country, 1)'
);
$addressInsert->execute([
    'user_id' => $customerId,
    'full_name' => 'CI Customer',
    'phone' => '+8801000000000',
    'address' => 'CI concurrency address',
    'city' => 'Dhaka',
    'country' => 'Bangladesh',
]);
$addressId = (int) $connection->lastInsertId();
$raceSessions = ['ci-' . strtolower($fixture) . '-race-a', 'ci-' . strtolower($fixture) . '-race-b'];
$raceOrders = ['CI-' . $fixture . '-RACE-A', 'CI-' . $fixture . '-RACE-B'];
$cartInsert = $connection->prepare('INSERT INTO carts (user_id, session_id) VALUES (:user_id, :session_id)');
$cartItemInsert = $connection->prepare(
    'INSERT INTO cart_items (cart_id, product_id, quantity, unit_price) '
    . 'VALUES (:cart_id, :product_id, 1, :unit_price)'
);
foreach ($raceSessions as $sessionId) {
    $cartInsert->execute(['user_id' => $customerId, 'session_id' => $sessionId]);
    $cartId = (int) $connection->lastInsertId();
    $cartItemInsert->execute([
        'cart_id' => $cartId,
        'product_id' => $physicalProduct['id'],
        'unit_price' => $physicalProduct['price'],
    ]);
}

if (!function_exists('pcntl_fork') || !function_exists('stream_socket_pair')) {
    throw new RuntimeException('The concurrent inventory integration test requires pcntl and Unix socket pairs.');
}

$workers = [];
foreach ($raceSessions as $sessionId) {
    $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    if ($sockets === false) {
        throw new RuntimeException('Could not create a synchronization socket for the stock concurrency test.');
    }
    $pid = pcntl_fork();
    if ($pid === -1) {
        throw new RuntimeException('Could not start a concurrent checkout worker.');
    }
    if ($pid === 0) {
        fclose($sockets[0]);
        foreach ($workers as $worker) {
            fclose($worker['socket']);
        }
        if (fgets($sockets[1]) === false) {
            exit(2);
        }

        try {
            $workerConnection = Database::connect($config['database']);
            $order = (new OrderRepository($workerConnection))->createFromCart(
                $sessionId,
                $customerId,
                $addressId
            );
            fwrite($sockets[1], 'success:' . $order['order_number'] . "\n");
            exit(0);
        } catch (CheckoutException $exception) {
            fwrite($sockets[1], "rejected\n");
            exit(0);
        } catch (Throwable $exception) {
            fwrite($sockets[1], 'error:' . $exception::class . ':' . $exception->getMessage() . "\n");
            exit(1);
        }
    }

    fclose($sockets[1]);
    $workers[] = ['pid' => $pid, 'socket' => $sockets[0]];
}

foreach ($workers as $worker) {
    fwrite($worker['socket'], "go\n");
    fflush($worker['socket']);
}
$checkoutResults = [];
foreach ($workers as $worker) {
    $checkoutResults[] = trim((string) fgets($worker['socket']));
    fclose($worker['socket']);
    pcntl_waitpid($worker['pid'], $workerStatus);
    if (!pcntl_wifexited($workerStatus) || pcntl_wexitstatus($workerStatus) !== 0) {
        throw new RuntimeException('A checkout concurrency worker terminated unexpectedly.');
    }
}
if (count(array_filter($checkoutResults, static fn (string $result): bool => str_starts_with($result, 'success:'))) !== 1
    || count(array_filter($checkoutResults, static fn (string $result): bool => $result === 'rejected')) !== 1
) {
    throw new RuntimeException('Concurrent checkouts exceeded available stock or failed unexpectedly: ' . implode(', ', $checkoutResults));
}
$winningOrderNumber = substr(
    (string) current(array_filter(
        $checkoutResults,
        static fn (string $result): bool => str_starts_with($result, 'success:')
    )),
    strlen('success:')
);
$checkoutSnapshot = $connection->prepare(
    'SELECT o.status, o.final_total, oi.unit_price, oi.quantity, c.session_id, COUNT(ci.id) AS cart_items '
    . 'FROM orders o JOIN order_items oi ON oi.order_id = o.id '
    . 'JOIN carts c ON c.session_id IN (:session_a, :session_b) '
    . 'LEFT JOIN cart_items ci ON ci.cart_id = c.id '
    . 'WHERE o.order_number = :order_number '
    . 'GROUP BY o.id, oi.id, c.id'
);
$checkoutSnapshot->execute([
    'session_a' => $raceSessions[0],
    'session_b' => $raceSessions[1],
    'order_number' => $winningOrderNumber,
]);
$checkoutSnapshotRows = $checkoutSnapshot->fetchAll();
$winningCartSession = null;
foreach ($checkoutSnapshotRows as $checkoutSnapshotRow) {
    if ((int) $checkoutSnapshotRow['cart_items'] === 0) {
        $winningCartSession = $checkoutSnapshotRow['session_id'];
    }
}
if ($checkoutSnapshotRows === []
    || $checkoutSnapshotRows[0]['status'] !== 'payment_pending'
    || (float) $checkoutSnapshotRows[0]['unit_price'] !== (float) $physicalProduct['price']
    || (float) $checkoutSnapshotRows[0]['final_total'] !== (float) $physicalProduct['price']
    || (int) $checkoutSnapshotRows[0]['quantity'] !== 1
    || $winningCartSession === null
) {
    throw new RuntimeException('Checkout did not snapshot server-side pricing or clear the winning cart.');
}
$stockQuery->execute(['product_id' => $physicalProduct['id']]);
if ((int) $stockQuery->fetchColumn() !== 0) {
    throw new RuntimeException('Exactly one concurrent checkout should reserve the single available unit.');
}

$expireRaceOrder = $connection->prepare(
    "UPDATE orders SET reservation_expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) "
    . "WHERE order_number IN (:order_a, :order_b) AND status = 'payment_pending'"
);
$expireRaceOrder->execute(['order_a' => $raceOrders[0], 'order_b' => $raceOrders[1]]);
$orders = new OrderRepository($connection);
if ($orders->releaseExpiredReservations() !== 1) {
    throw new RuntimeException('The winning checkout reservation was not released exactly once.');
}
$stockQuery->execute(['product_id' => $physicalProduct['id']]);
if ((int) $stockQuery->fetchColumn() !== 1 || $orders->releaseExpiredReservations() !== 0) {
    throw new RuntimeException('Repeated reservation cleanup did not preserve single-unit inventory.');
}
$cleanupCarts = $connection->prepare('DELETE FROM carts WHERE session_id IN (:session_a, :session_b)');
$cleanupCarts->execute(['session_a' => $raceSessions[0], 'session_b' => $raceSessions[1]]);
$deleteRaceOrders = $connection->prepare(
    'DELETE FROM orders WHERE order_number IN (:order_a, :order_b) AND status = :status'
);
$deleteRaceOrders->execute([
    'order_a' => $raceOrders[0],
    'order_b' => $raceOrders[1],
    'status' => 'cancelled',
]);
$restoreOriginalStock = $connection->prepare(
    'UPDATE products SET stock_quantity = :stock WHERE id = :product_id'
);
$restoreOriginalStock->execute(['stock' => $originalPhysicalStock, 'product_id' => $physicalProduct['id']]);

$reserveStock = $connection->prepare(
    'UPDATE products SET stock_quantity = stock_quantity - 1 WHERE id = :product_id AND stock_quantity > 0'
);
$reserveStock->execute(['product_id' => $physicalProduct['id']]);
if ($reserveStock->rowCount() !== 1) {
    throw new RuntimeException('Could not reserve test inventory for payment callback coverage.');
}
$riskOrderId = $createOrder(
    $customerId,
    'payment_pending',
    'CI-' . $fixture . '-RISK',
    $physicalProduct,
    'physical'
);
$riskTransactionId = 'HY' . strtoupper(bin2hex(random_bytes(12)));
$insertPayment($riskOrderId, $riskTransactionId, (string) $physicalProduct['price']);
$riskValidation = $validatedPayment;
$riskValidation['tran_id'] = $riskTransactionId;
$riskValidation['val_id'] = 'CI-RISK-VALIDATION-' . $fixture;
$riskValidation['amount'] = (string) $physicalProduct['price'];
$riskValidation['risk_level'] = '1';
$riskResult = $payments->confirmValidatedPayment($riskValidation);
if ($riskResult['order_id'] !== $riskOrderId || $riskResult['already_processed']) {
    throw new RuntimeException('A verified risky payment was not routed to manual review.');
}
$riskStatus = $connection->prepare(
    'SELECT o.status AS order_status, pt.status AS payment_status '
    . 'FROM orders o JOIN payment_transactions pt ON pt.order_id = o.id WHERE o.id = :order_id'
);
$riskStatus->execute(['order_id' => $riskOrderId]);
if ($riskStatus->fetch() !== ['order_status' => 'payment_review', 'payment_status' => 'review_required']) {
    throw new RuntimeException('A risky payment did not persist its order and transaction review states.');
}
$stockQuery->execute(['product_id' => $physicalProduct['id']]);
$stockAfterRisk = (int) $stockQuery->fetchColumn();
$duplicateRisk = $payments->confirmValidatedPayment($riskValidation);
$differentRiskValidation = $riskValidation;
$differentRiskValidation['val_id'] .= '-OTHER';
try {
    $payments->confirmValidatedPayment($differentRiskValidation);
    throw new RuntimeException('A different validation reference was accepted for a reviewed payment.');
} catch (PaymentException) {
}
$stockQuery->execute(['product_id' => $physicalProduct['id']]);
if (!$duplicateRisk['already_processed'] || (int) $stockQuery->fetchColumn() !== $stockAfterRisk) {
    throw new RuntimeException('Repeating a risky callback restored inventory more than once.');
}

$reserveStock->execute(['product_id' => $physicalProduct['id']]);
if ($reserveStock->rowCount() !== 1) {
    throw new RuntimeException('Could not reserve test inventory for expired-callback coverage.');
}
$lateOrderId = $createOrder(
    $customerId,
    'payment_pending',
    'CI-' . $fixture . '-LATE',
    $physicalProduct,
    'physical'
);
$lateTransactionId = 'HY' . strtoupper(bin2hex(random_bytes(12)));
$insertPayment($lateOrderId, $lateTransactionId, (string) $physicalProduct['price']);
$lateStatus = $connection->prepare(
    'SELECT o.status FROM orders o WHERE o.id = :order_id'
);
$invalidValidations = [];
$failedValidation = $validatedPayment;
$failedValidation['tran_id'] = $lateTransactionId;
$failedValidation['status'] = 'FAILED';
$invalidValidations[] = $failedValidation;
$wrongAmountValidation = $validatedPayment;
$wrongAmountValidation['tran_id'] = $lateTransactionId;
$wrongAmountValidation['amount'] = '1.00';
$invalidValidations[] = $wrongAmountValidation;
$wrongCurrencyValidation = $validatedPayment;
$wrongCurrencyValidation['tran_id'] = $lateTransactionId;
$wrongCurrencyValidation['currency_type'] = 'USD';
$invalidValidations[] = $wrongCurrencyValidation;
foreach ($invalidValidations as $invalidValidation) {
    try {
        $payments->confirmValidatedPayment($invalidValidation);
        throw new RuntimeException('An unverified, wrong-amount, or wrong-currency payment was accepted.');
    } catch (PaymentException) {
    }
}
$paymentState = $connection->prepare(
    'SELECT o.status AS order_status, pt.status AS payment_status '
    . 'FROM orders o JOIN payment_transactions pt ON pt.order_id = o.id WHERE o.id = :order_id'
);
$paymentState->execute(['order_id' => $lateOrderId]);
if ($paymentState->fetch() !== ['order_status' => 'payment_pending', 'payment_status' => 'pending']) {
    throw new RuntimeException('Rejected payment callback data changed the pending order or payment.');
}
$expireLateOrder = $connection->prepare(
    'UPDATE orders SET reservation_expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) WHERE id = :order_id'
);
$expireLateOrder->execute(['order_id' => $lateOrderId]);
$stockQuery->execute(['product_id' => $physicalProduct['id']]);
$stockBeforeLatePayment = (int) $stockQuery->fetchColumn();
if ($orders->releaseExpiredReservations() < 1) {
    throw new RuntimeException('The expired checkout reservation was not released.');
}
$lateValidation = $validatedPayment;
$lateValidation['tran_id'] = $lateTransactionId;
$lateValidation['val_id'] = 'CI-LATE-VALIDATION-' . $fixture;
$lateValidation['amount'] = (string) $physicalProduct['price'];
$lateResult = $payments->confirmValidatedPayment($lateValidation);
$duplicateLateResult = $payments->confirmValidatedPayment($lateValidation);
$lateStatus->execute(['order_id' => $lateOrderId]);
$stockQuery->execute(['product_id' => $physicalProduct['id']]);
if ($lateResult['order_id'] !== $lateOrderId || $lateResult['already_processed']
    || !$duplicateLateResult['already_processed']
    || $lateStatus->fetchColumn() !== 'payment_review'
    || (int) $stockQuery->fetchColumn() !== $stockBeforeLatePayment
) {
    throw new RuntimeException('A late verified payment was not isolated for manual review without double-restoring stock.');
}

echo "MySQL integration checks passed: migrations, catalog visibility, payment idempotency/risk/late callbacks, file access/revocation, course ownership/progress, shipment lifecycle, and concurrent checkout stock safety.\n";
