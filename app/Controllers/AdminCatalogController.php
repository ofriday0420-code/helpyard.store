<?php

namespace Helpyard\App\Controllers;

use Helpyard\App\Core\AdminCatalogException;
use Helpyard\App\Core\Database;
use Helpyard\App\Core\Request;
use Helpyard\App\Core\Response;
use Helpyard\App\Core\SessionSecurity;
use Helpyard\App\Repositories\AdminCatalogRepository;
use Helpyard\App\Services\AdminCatalogPolicy;
use Helpyard\App\Services\ProductImageUploadService;
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

    public function productImages(array $params, ?Request $request = null): Response
    {
        SessionSecurity::start();
        if (!$this->isAdmin()) {
            return $this->forbiddenOrLogin();
        }

        $productId = CartController::positiveInteger($params['id'] ?? null, PHP_INT_MAX);
        if ($productId === null) {
            return new Response(404, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Product not found.');
        }

        try {
            $product = (new AdminCatalogRepository(Database::connect($this->databaseConfig)))
                ->productImageWorkspace($productId);
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }
        if ($product === null) {
            return new Response(404, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Product not found.');
        }

        $remainingImageSlots = max(0, ProductImageUploadService::MAX_IMAGES_PER_PRODUCT - count($product['images']));
        $csrfToken = SessionSecurity::csrfToken();
        $notice = $this->consumeFlash('admin_catalog_notice');
        $error = $this->consumeFlash('admin_catalog_error');
        $title = 'Product images — ' . $product['name'];
        $description = 'Manage the images and accessible descriptions for this product.';
        ob_start();
        require __DIR__ . '/../Views/admin/product-images.php';
        $html = ob_get_clean();
        if ($html === false) {
            throw new RuntimeException('Could not render the product image manager.');
        }

        return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $html);
    }

    public function uploadProductImage(array $params, Request $request): Response
    {
        if (($response = $this->guardMutation($request)) !== null) {
            return $response;
        }
        $productId = CartController::positiveInteger($params['id'] ?? null, PHP_INT_MAX);
        if ($productId === null) {
            return new Response(404, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Product not found.');
        }
        $altText = $this->validatedImageAltText($request->body()['alt_text'] ?? null);
        if ($altText === null) {
            return $this->imageManagerError($productId, 'Enter descriptive alt text up to 255 characters.');
        }
        $upload = $request->files()['image'] ?? null;
        if (!is_array($upload)) {
            return $this->imageManagerError($productId, 'Choose a JPEG, PNG, or WebP image.');
        }

        try {
            $storedImage = ProductImageUploadService::store(
                $upload,
                $productId,
                dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public'
            );
        } catch (RuntimeException $exception) {
            return $this->imageManagerError($productId, $exception->getMessage());
        }

        try {
            (new AdminCatalogRepository(Database::connect($this->databaseConfig)))->addProductImage(
                $productId,
                $storedImage['image_url'],
                $altText,
                (int) $_SESSION['user_id']
            );
        } catch (AdminCatalogException $exception) {
            $this->removeUnregisteredImage($storedImage['image_url']);
            return $this->imageManagerError($productId, $exception->getMessage());
        } catch (PDOException | RuntimeException $exception) {
            $this->removeUnregisteredImage($storedImage['image_url']);
            return $this->unavailable($exception);
        }

        $_SESSION['admin_catalog_notice'] = 'Product image uploaded.';
        return $this->redirect('/admin/catalog/products/' . $productId . '/images');
    }

    public function updateProductImageAlt(array $params, Request $request): Response
    {
        if (($response = $this->guardMutation($request)) !== null) {
            return $response;
        }
        $productId = CartController::positiveInteger($params['product_id'] ?? null, PHP_INT_MAX);
        $imageId = CartController::positiveInteger($params['image_id'] ?? null, PHP_INT_MAX);
        $altText = $this->validatedImageAltText($request->body()['alt_text'] ?? null);
        if ($productId === null || $imageId === null) {
            return new Response(404, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Product image not found.');
        }
        if ($altText === null) {
            return $this->imageManagerError($productId, 'Enter descriptive alt text up to 255 characters.');
        }

        try {
            $updated = (new AdminCatalogRepository(Database::connect($this->databaseConfig)))
                ->updateProductImageAltText($productId, $imageId, $altText, (int) $_SESSION['user_id']);
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }
        if (!$updated) {
            return $this->imageManagerError($productId, 'That product image no longer exists.');
        }

        $_SESSION['admin_catalog_notice'] = 'Image description updated.';
        return $this->redirect('/admin/catalog/products/' . $productId . '/images');
    }

    public function deleteProductImage(array $params, Request $request): Response
    {
        if (($response = $this->guardMutation($request)) !== null) {
            return $response;
        }
        $productId = CartController::positiveInteger($params['product_id'] ?? null, PHP_INT_MAX);
        $imageId = CartController::positiveInteger($params['image_id'] ?? null, PHP_INT_MAX);
        if ($productId === null || $imageId === null) {
            return new Response(404, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Product image not found.');
        }

        try {
            $imageUrl = (new AdminCatalogRepository(Database::connect($this->databaseConfig)))
                ->deleteProductImage($productId, $imageId, (int) $_SESSION['user_id']);
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }
        if ($imageUrl === null) {
            return $this->imageManagerError($productId, 'That product image no longer exists.');
        }

        try {
            ProductImageUploadService::removeManagedImage(
                $imageUrl,
                dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public'
            );
        } catch (RuntimeException $exception) {
            error_log('Product image row was removed, but its file could not be deleted: ' . $exception->getMessage());
        }

        $_SESSION['admin_catalog_notice'] = 'Product image removed.';
        return $this->redirect('/admin/catalog/products/' . $productId . '/images');
    }

    private function validatedImageAltText(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $altText = trim($value);
        $length = preg_match_all('/./us', $altText);
        if ($altText === '' || $length === false || $length > 255 || preg_match('/[\x00-\x1F\x7F]/', $altText)) {
            return null;
        }

        return $altText;
    }

    private function imageManagerError(int $productId, string $message): Response
    {
        SessionSecurity::start();
        $_SESSION['admin_catalog_error'] = $message;

        return $this->redirect('/admin/catalog/products/' . $productId . '/images');
    }

    private function removeUnregisteredImage(string $imageUrl): void
    {
        try {
            ProductImageUploadService::removeManagedImage(
                $imageUrl,
                dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public'
            );
        } catch (RuntimeException $exception) {
            error_log('Could not remove an unregistered product image: ' . $exception->getMessage());
        }
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
