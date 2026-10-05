<?php

require __DIR__ . '/../bootstrap.php';

use Helpyard\App\Core\Environment;
use Helpyard\App\Core\Request;
use Helpyard\App\Core\SqlScript;
use Helpyard\App\Controllers\CatalogController;
use Helpyard\App\Controllers\AuthController;
use Helpyard\App\Controllers\CartController;
use Helpyard\App\Controllers\CheckoutController;
use Helpyard\App\Repositories\CatalogRepository;
use Helpyard\App\Core\Response;
use Helpyard\App\Core\Router;
use Helpyard\App\Core\SessionSecurity;

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

    if (CartController::positiveInteger('1', 99) !== 1
        || CartController::positiveInteger('99', 99) !== 99
        || CartController::positiveInteger('0', 99) !== null
        || CartController::positiveInteger('100', 99) !== null
        || CartController::positiveInteger(['1'], 99) !== null
    ) {
        throw new RuntimeException('Cart quantities must be positive integers capped at 99.');
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
    $_SESSION['user_id'] = 123;
    $_SESSION['user_role'] = 'customer';
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

    echo "Environment, SQL, routing, catalog, cart, and authentication security tests passed.\n";
} finally {
    unlink($file);
    putenv($loadedName);
    putenv($overrideName);
    unset($_ENV[$loadedName], $_ENV[$overrideName], $_SERVER[$loadedName], $_SERVER[$overrideName]);
}
