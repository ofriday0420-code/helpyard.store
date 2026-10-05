<?php

require __DIR__ . '/../bootstrap.php';

use Helpyard\App\Core\Environment;
use Helpyard\App\Core\Request;
use Helpyard\App\Core\SqlScript;
use Helpyard\App\Controllers\CatalogController;
use Helpyard\App\Controllers\AuthController;
use Helpyard\App\Controllers\CartController;
use Helpyard\App\Controllers\CheckoutController;
use Helpyard\App\Controllers\PaymentController;
use Helpyard\App\Controllers\AdminFulfillmentController;
use Helpyard\App\Controllers\DownloadController;
use Helpyard\App\Controllers\CourseController;
use Helpyard\App\Repositories\CatalogRepository;
use Helpyard\App\Core\Response;
use Helpyard\App\Core\Router;
use Helpyard\App\Core\SessionSecurity;
use Helpyard\App\Services\SSLCommerzGateway;
use Helpyard\App\Services\FulfillmentPolicy;
use Helpyard\App\Services\DigitalDeliveryService;

$file = tempnam(sys_get_temp_dir(), 'helpyard-env-');
if ($file === false) {
    throw new RuntimeException('Could not create a temporary environment test file.');
}

$loadedName = 'HELPYARD_TEST_' . strtoupper(bin2hex(random_bytes(4)));
$overrideName = $loadedName . '_OVERRIDE';

try {
    file_put_contents($file, $loadedName . "=\"loaded value\"\n" . $overrideName . "=from-file\n");
    putenv($overrideName . '=from-process');

    Environment::load($file);

    if (getenv($loadedName) !== 'loaded value') {
        throw new RuntimeException('Quoted environment values were not loaded correctly.');
    }

    if (getenv($overrideName) !== 'from-process') {
        throw new RuntimeException('Existing process environment values were overwritten.');
    }

    $statements = SqlScript::statements(
        "INSERT INTO sample (value) VALUES ('contains;separator');\n"
        . "-- statement separator in comment ;\n"
        . "SELECT \"quoted;value\";\n"
    );
    if (count($statements) !== 2) {
        throw new RuntimeException('SQL scripts were not split into the expected statements.');
    }
    $migrationFiles = glob(__DIR__ . '/../database/migrations/*.sql');
    if ($migrationFiles === false || $migrationFiles === []) {
        throw new RuntimeException('Database migration files could not be found.');
    }
    foreach ($migrationFiles as $migrationFile) {
        $migrationContents = file_get_contents($migrationFile);
        if ($migrationContents === false || SqlScript::statements($migrationContents) === []) {
            throw new RuntimeException('A database migration is empty or invalid: ' . basename($migrationFile));
        }
        if (FulfillmentPolicy::nextStatus('paid', 'start_processing') !== 'processing'
            || FulfillmentPolicy::nextStatus('processing', 'mark_shipped') !== 'shipped'
            || FulfillmentPolicy::nextStatus('shipped', 'mark_delivered') !== 'delivered'
            || FulfillmentPolicy::nextStatus('payment_pending', 'start_processing') !== null
            || FulfillmentPolicy::nextStatus('delivered', 'mark_shipped') !== null
            || FulfillmentPolicy::nextStatus('paid', 'mark_delivered') !== null
        ) {
            throw new RuntimeException('Fulfillment order status transitions are not constrained correctly.');
        }
        $validShipment = FulfillmentPolicy::validateShipmentDetails('  Example Carrier  ', '  TRACK-123  ');
        if ($validShipment['carrier'] !== 'Example Carrier' || $validShipment['tracking_number'] !== 'TRACK-123'
            || $validShipment['error'] !== null
            || FulfillmentPolicy::validateShipmentDetails('', 'TRACK-123')['error'] === null
            || FulfillmentPolicy::validateShipmentDetails('Carrier', str_repeat('x', 121))['error'] === null
            || !FulfillmentPolicy::canShipProductTypes(['physical', 'book'])
            || FulfillmentPolicy::canShipProductTypes(['physical', 'course'])
            || FulfillmentPolicy::canShipProductTypes([])
        ) {
            throw new RuntimeException('Shipment details or physical fulfillment eligibility were not validated.');
        }
    }

    $catalog = new CatalogController([]);
    $invalidCategory = new Request(
        ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/v1/products'],
        ['category' => '../private']
    );
    if ($catalog->index([], $invalidCategory)->status() !== 400) {
        throw new RuntimeException('Invalid category filters should be rejected before querying the database.');
    }

    $invalidType = new Request(
        ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/v1/products'],
        ['type' => 'unrecognized']
    );
    if ($catalog->index([], $invalidType)->status() !== 400) {
        throw new RuntimeException('Invalid product types should be rejected before querying the database.');
    }

    $invalidSearch = new Request(
        ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/v1/products'],
        ['q' => str_repeat('x', 121)]
    );
    if ($catalog->index([], $invalidSearch)->status() !== 400) {
        throw new RuntimeException('Overlong search terms should be rejected before querying the database.');
    }

    if (CatalogRepository::escapeSearchTerm('100%_ready!') !== '100!%!_ready!!') {
        throw new RuntimeException('Search wildcard characters were not escaped.');
    }

    $privateRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'helpyard-private-' . bin2hex(random_bytes(6));
    if (!mkdir($privateRoot) || !file_put_contents($privateRoot . DIRECTORY_SEPARATOR . 'sample.zip', 'private-test-content')) {
        throw new RuntimeException('Could not prepare temporary private download test storage.');
    }
    try {
        $privatePath = DigitalDeliveryService::resolvePrivateFile(
            $privateRoot,
            'sample.zip',
            dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public'
        );
        if (file_get_contents($privatePath) !== 'private-test-content'
            || DigitalDeliveryService::safeDownloadName('..\\release.zip') !== 'release.zip'
            || DigitalDeliveryService::safeDownloadName("safe.zip\r\nX-Test: injected") !== 'safe.zipX-Test: injected'
        ) {
            throw new RuntimeException('Private download resolution or filename normalization failed.');
        }
        foreach (['../outside.zip', 'nested/../../outside.zip', 'C:\\outside.zip', '/outside.zip'] as $invalidKey) {
            try {
                DigitalDeliveryService::resolvePrivateFile(
                    $privateRoot,
                    $invalidKey,
                    dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public'
                );
                throw new RuntimeException('A private download path traversal was accepted.');
            } catch (RuntimeException $exception) {
                if ($exception->getMessage() === 'A private download path traversal was accepted.') {
                    throw $exception;
                }
            }
        }
        try {
            DigitalDeliveryService::resolvePrivateFile(
                dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public',
                'index.php',
                dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public'
            );
            throw new RuntimeException('A public directory was accepted as private download storage.');
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'A public directory was accepted as private download storage.') {
                throw $exception;
            }
        }
        try {
            DigitalDeliveryService::resolvePrivateFile(
                dirname(__DIR__),
                'public/index.php',
                dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public'
            );
            throw new RuntimeException('A file under the public web root was accepted as a private download.');
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'A file under the public web root was accepted as a private download.') {
                throw $exception;
            }
        }
        $streamedResponse = new Response(200, [], static function (): void {
            echo 'streamed-content';
        });
        ob_start();
        $streamedResponse->send();
        $streamedBody = ob_get_clean();
        if ($streamedBody !== 'streamed-content') {
            throw new RuntimeException('Streaming responses did not emit their body.');
        }
    } finally {
        unlink($privateRoot . DIRECTORY_SEPARATOR . 'sample.zip');
        rmdir($privateRoot);
    }

    if (CartController::positiveInteger('1', 99) !== 1
        || CartController::positiveInteger('99', 99) !== 99
        || CartController::positiveInteger('0', 99) !== null
        || CartController::positiveInteger('100', 99) !== null
        || CartController::positiveInteger(['1'], 99) !== null
    ) {
        throw new RuntimeException('Cart quantities must be positive integers capped at 99.');
    }
    if (SSLCommerzGateway::normalizedAmount('10') !== '10.00'
        || SSLCommerzGateway::normalizedAmount('500000.01') !== '500000.01'
        || SSLCommerzGateway::normalizedAmount('-1') !== null
        || SSLCommerzGateway::normalizedAmount('1e2') !== null
        || SSLCommerzGateway::normalizedAmount('5000000') !== null
        || !SSLCommerzGateway::isTrustedGatewayUrl(
            'https://sandbox.sslcommerz.com/gwprocess/v4/gw.php?Q=example',
            'sandbox.sslcommerz.com'
        )
        || SSLCommerzGateway::isTrustedGatewayUrl(
            'https://attacker.example/gateway',
            'sandbox.sslcommerz.com'
        )
        || SSLCommerzGateway::isTrustedGatewayUrl(
            'https://user@sandbox.sslcommerz.com/gateway',
            'sandbox.sslcommerz.com'
        )
        || SSLCommerzGateway::isTrustedGatewayUrl(
            'https://user:password@sandbox.sslcommerz.com/gateway',
            'sandbox.sslcommerz.com'
        )
    ) {
        throw new RuntimeException('Payment amounts or hosted gateway URLs were not validated safely.');
    }

    if (AuthController::validateRegistration('Customer Name', 'customer@example.com', 'long-enough-password') !== []) {
        throw new RuntimeException('Valid customer registration details were rejected.');
    }
    if (AuthController::validateRegistration('Name', 'invalid-email', 'short') === []) {
        throw new RuntimeException('Invalid registration data was accepted.');
    }
    $validAddress = [
        'full_name' => 'Customer Name',
        'phone' => '+8801000000000',
        'address_line_1' => '12 Sample Road',
        'address_line_2' => '',
        'city' => 'Dhaka',
        'postal_code' => '',
        'country' => 'Bangladesh',
    ];
    if (AuthController::validateAddress($validAddress) !== []) {
        throw new RuntimeException('Valid customer address was rejected.');
    }
    $invalidAddress = $validAddress;
    $invalidAddress['country'] = '';
    if (AuthController::validateAddress($invalidAddress) === []) {
        throw new RuntimeException('Address without a required country was accepted.');
    }

    $csrfToken = SessionSecurity::csrfToken();
    if (strlen($csrfToken) !== 64 || !SessionSecurity::verifyCsrfToken($csrfToken)
        || SessionSecurity::verifyCsrfToken('invalid-token')
    ) {
        throw new RuntimeException('CSRF token generation or validation failed.');
    }
    if ((new CartController([]))->addItem([], new Request(
        ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/cart/items'],
        [],
        ['csrf_token' => 'invalid-token', 'product_id' => '1', 'quantity' => '1']
    ))->status() !== 400) {
        throw new RuntimeException('Cart mutations without a valid CSRF token should be rejected.');
    }
    if ((new CheckoutController([]))->show()->status() !== 303) {
        throw new RuntimeException('Checkout should require a signed-in customer.');
    }
    if ((new PaymentController([], []))->initiate(['id' => '1'], new Request(
        ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/orders/1/pay'],
        [],
        ['csrf_token' => 'invalid-token']
    ))->status() !== 303) {
        throw new RuntimeException('Payment initiation should require a signed-in customer.');
    }
    if ((new PaymentController([], []))->ipn([], new Request(
        ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/payments/ipn']
    ))->status() !== 400) {
        throw new RuntimeException('Payment notifications without provider references should be rejected.');
    }
    if ((new AdminFulfillmentController([]))->index()->status() !== 303) {
        throw new RuntimeException('The fulfillment console should require an authenticated administrator.');
    }
    if ((new DownloadController([], sys_get_temp_dir()))->index()->status() !== 303) {
        throw new RuntimeException('The customer downloads page should require authentication.');
    }
    if ((new CourseController([]))->index()->status() !== 303
        || (new CourseController([]))->show(['id' => '1'])->status() !== 303
    ) {
        throw new RuntimeException('Customer course pages should require authentication.');
    }
    $_SESSION['user_id'] = 123;
    $_SESSION['user_role'] = 'customer';
    if ((new AdminFulfillmentController([]))->index()->status() !== 403) {
        throw new RuntimeException('Customer accounts must not access the fulfillment console.');
    }
    if ((new DownloadController([], sys_get_temp_dir()))->download(['id' => '../other'], new Request(
        ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/account/downloads/invalid']
    ))->status() !== 404) {
        throw new RuntimeException('Invalid download entitlement identifiers should be rejected.');
    }
    if ((new CourseController([]))->show(['id' => 'not-an-id'])->status() !== 404) {
        throw new RuntimeException('Invalid course identifiers should be rejected.');
    }
    if ((new CheckoutController([]))->createOrder([], new Request(
        ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/checkout'],
        [],
        ['csrf_token' => 'invalid-token', 'address_id' => '1']
    ))->status() !== 400) {
        throw new RuntimeException('Order creation without a valid CSRF token should be rejected.');
    }
    unset($_SESSION['user_id'], $_SESSION['user_role']);

    $router = new Router();
    $router->get('/products/{slug}', static function (array $params): Response {
        return new Response(200, [], $params);
    });
    $routeResponse = $router->dispatch(new Request(
        ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/products/test-product']
    ));
    ob_start();
    $routeResponse->send();
    $routeBody = ob_get_clean();
    $routePayload = json_decode($routeBody, true);
    if ($routeResponse->status() !== 200 || ($routePayload['slug'] ?? null) !== 'test-product') {
        throw new RuntimeException('Named route parameters were not passed to the route handler.');
    }

    if ($catalog->show(['slug' => '../invalid'], new Request(
        ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/v1/products/invalid']
    ))->status() !== 404) {
        throw new RuntimeException('Invalid product slugs should be rejected before querying the database.');
    }

    try {
        SqlScript::statements("SELECT 'unterminated;");
        throw new RuntimeException('Unterminated SQL quotes should be rejected.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'SQL script contains an unterminated quoted value or block comment.') {
            throw $exception;
        }
    }

    echo "Environment, SQL, routing, catalog, cart, payment, fulfillment, protected download, and authentication tests passed.\n";
} finally {
    unlink($file);
    putenv($loadedName);
    putenv($overrideName);
    unset($_ENV[$loadedName], $_ENV[$overrideName], $_SERVER[$loadedName], $_SERVER[$overrideName]);
}
