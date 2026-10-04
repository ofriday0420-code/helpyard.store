<?php

require __DIR__ . '/../bootstrap.php';

use Helpyard\App\Core\Environment;
use Helpyard\App\Core\Request;
use Helpyard\App\Core\SqlScript;
use Helpyard\App\Controllers\CatalogController;
use Helpyard\App\Repositories\CatalogRepository;
use Helpyard\App\Core\Response;
use Helpyard\App\Core\Router;

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

    echo "Environment, SQL script, routing, and catalog validation tests passed.\n";
} finally {
    unlink($file);
    putenv($loadedName);
    putenv($overrideName);
    unset($_ENV[$loadedName], $_ENV[$overrideName], $_SERVER[$loadedName], $_SERVER[$overrideName]);
}
