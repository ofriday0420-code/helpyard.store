<?php

namespace Helpyard\App\Controllers;

use Helpyard\App\Core\AdminCatalogException;
use Helpyard\App\Core\Database;
use Helpyard\App\Core\Request;
use Helpyard\App\Core\Response;
use Helpyard\App\Core\SessionSecurity;
use Helpyard\App\Repositories\AdminCatalogRepository;
use Helpyard\App\Services\AdminCatalogPolicy;
use PDOException;
use RuntimeException;

class AdminCatalogController
{
    public function __construct(private array $databaseConfig)
    {
    }

    public function index(array $params = [], ?Request $request = null): Response
    {
        SessionSecurity::start();
        if (!$this->isAdmin()) {
            return $this->forbiddenOrLogin();
        }

        try {
            $catalog = (new AdminCatalogRepository(Database::connect($this->databaseConfig)))->dashboard();
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }

        $categories = $catalog['categories'];
        $activeCategories = $catalog['active_categories'];
        $products = $catalog['products'];
        $productTypes = \Helpyard\App\Repositories\CatalogRepository::productTypes();
        $csrfToken = SessionSecurity::csrfToken();
        $notice = $this->consumeFlash('admin_catalog_notice');
        $error = $this->consumeFlash('admin_catalog_error');
        $title = 'Catalog administration';
        $description = 'Manage product listings, categories, and stock.';
        ob_start();
        require __DIR__ . '/../Views/admin/catalog.php';
        $html = ob_get_clean();
        if ($html === false) {
            throw new RuntimeException('Could not render the catalog administration page.');
        }

        return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $html);
    }

    public function createCategory(array $params, Request $request): Response
    {
        if (($response = $this->guardMutation($request)) !== null) {
            return $response;
        }

        $category = AdminCatalogPolicy::validateCategory($request->body());
        if ($category['error'] !== null) {
            return $this->validationError($category['error']);
        }

        try {
            (new AdminCatalogRepository(Database::connect($this->databaseConfig)))
                ->createCategory($category['data'], (int) $_SESSION['user_id']);
        } catch (AdminCatalogException $exception) {
            return $this->validationError($exception->getMessage());
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }

        $_SESSION['admin_catalog_notice'] = 'Category created.';
        return $this->redirect('/admin/catalog');
    }

    public function updateCategory(array $params, Request $request): Response
    {
        if (($response = $this->guardMutation($request)) !== null) {
            return $response;
        }
        $categoryId = CartController::positiveInteger($params['id'] ?? null, PHP_INT_MAX);
        if ($categoryId === null) {
            return $this->validationError('Choose a valid category.');
        }
        $category = AdminCatalogPolicy::validateCategory($request->body());
        if ($category['error'] !== null) {
            return $this->validationError($category['error']);
        }
        $active = ($request->body()['is_active'] ?? null) === '1';

        try {
            $updated = (new AdminCatalogRepository(Database::connect($this->databaseConfig)))
                ->updateCategory($categoryId, $category['data'], $active, (int) $_SESSION['user_id']);
        } catch (AdminCatalogException $exception) {
            return $this->validationError($exception->getMessage());
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }
        if (!$updated) {
            return $this->validationError('That category does not exist.');
        }

        $_SESSION['admin_catalog_notice'] = 'Category updated.';
        return $this->redirect('/admin/catalog');
    }

    public function createProduct(array $params, Request $request): Response
    {
        if (($response = $this->guardMutation($request)) !== null) {
            return $response;
        }
        $product = AdminCatalogPolicy::validateProduct($request->body());
        if ($product['error'] !== null) {
            return $this->validationError($product['error']);
        }

        try {
            (new AdminCatalogRepository(Database::connect($this->databaseConfig)))
                ->createProduct($product['data'], (int) $_SESSION['user_id']);
        } catch (AdminCatalogException $exception) {
            return $this->validationError($exception->getMessage());
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }

        $_SESSION['admin_catalog_notice'] = 'Product created.';
        return $this->redirect('/admin/catalog');
    }

    public function updateProduct(array $params, Request $request): Response
    {
        if (($response = $this->guardMutation($request)) !== null) {
            return $response;
        }
        $productId = CartController::positiveInteger($params['id'] ?? null, PHP_INT_MAX);
        if ($productId === null) {
            return $this->validationError('Choose a valid product.');
        }
        $product = AdminCatalogPolicy::validateProduct($request->body());
        if ($product['error'] !== null) {
            return $this->validationError($product['error']);
        }

        try {
            $updated = (new AdminCatalogRepository(Database::connect($this->databaseConfig)))
                ->updateProduct($productId, $product['data'], (int) $_SESSION['user_id']);
        } catch (AdminCatalogException $exception) {
            return $this->validationError($exception->getMessage());
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }
        if (!$updated) {
            return $this->validationError('That product does not exist.');
        }

        $_SESSION['admin_catalog_notice'] = 'Product updated.';
        return $this->redirect('/admin/catalog');
    }

    public function updateVariantStock(array $params, Request $request): Response
    {
        if (($response = $this->guardMutation($request)) !== null) {
            return $response;
        }
        $variantId = CartController::positiveInteger($params['id'] ?? null, PHP_INT_MAX);
        $stock = AdminCatalogPolicy::validateStock($request->body()['stock_quantity'] ?? null);
        if ($variantId === null || $stock === null) {
            return $this->validationError('Enter a valid variant and stock quantity.');
        }

        try {
            $updated = (new AdminCatalogRepository(Database::connect($this->databaseConfig)))
                ->updateVariantStock($variantId, $stock, (int) $_SESSION['user_id']);
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }
        if (!$updated) {
            return $this->validationError('That product option does not exist.');
        }

        $_SESSION['admin_catalog_notice'] = 'Product option inventory updated.';
        return $this->redirect('/admin/catalog');
    }

    private function guardMutation(Request $request): ?Response
    {
        SessionSecurity::start();
        if (!$this->isAdmin()) {
            return $this->forbiddenOrLogin();
        }
        if (!SessionSecurity::verifyCsrfToken($request->body()['csrf_token'] ?? null)) {
            return new Response(400, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Your form session expired. Please try again.');
        }

        return null;
    }

    private function validationError(string $message): Response
    {
        SessionSecurity::start();
        $_SESSION['admin_catalog_error'] = $message;
        return $this->redirect('/admin/catalog');
    }

    private function isAdmin(): bool
    {
        return isset($_SESSION['user_id'], $_SESSION['user_role'])
            && $_SESSION['user_role'] === 'admin';
    }

    private function forbiddenOrLogin(): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return $this->redirect('/login');
        }
        return new Response(403, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Administrator access is required.');
    }

    private function consumeFlash(string $key): string
    {
        $value = $_SESSION[$key] ?? '';
        unset($_SESSION[$key]);
        return is_string($value) ? $value : '';
    }

    private function redirect(string $location): Response
    {
        return new Response(303, ['Location' => $location], '');
    }

    private function unavailable(PDOException | RuntimeException $exception): Response
    {
        error_log('Catalog administration failed: ' . $exception->getMessage());
        return new Response(503, ['Content-Type' => 'text/html; charset=UTF-8'], 'Catalog administration is temporarily unavailable.');
    }
}
