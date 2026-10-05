<?php

namespace Helpyard\App\Controllers;

use Helpyard\App\Core\Database;
use Helpyard\App\Core\CartException;
use Helpyard\App\Core\Request;
use Helpyard\App\Core\Response;
use Helpyard\App\Core\SessionSecurity;
use Helpyard\App\Repositories\CartRepository;
use PDOException;
use RuntimeException;

class CartController
{
    public function __construct(private array $databaseConfig)
    {
    }

    public function show(array $params = [], ?Request $request = null): Response
    {
        SessionSecurity::start();

        try {
            $cart = $this->repository()->contents($this->cartKey());
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }

        $title = 'Your cart';
        $description = 'Review products in your Helpyard.store cart.';
        $csrfToken = SessionSecurity::csrfToken();
        $error = $this->consumeFlash('cart_error');
        $notice = $this->consumeFlash('cart_notice');
        ob_start();
        require __DIR__ . '/../Views/cart.php';
        $html = ob_get_clean();
        if ($html === false) {
            throw new RuntimeException('Could not render the shopping cart.');
        }

        return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $html);
    }

    public function addItem(array $params, Request $request): Response
    {
        SessionSecurity::start();
        if (!SessionSecurity::verifyCsrfToken($request->body()['csrf_token'] ?? null)) {
            return new Response(400, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Your form session expired. Please try again.');
        }

        $productId = self::positiveInteger($request->body()['product_id'] ?? null, PHP_INT_MAX);
        $quantity = self::positiveInteger($request->body()['quantity'] ?? '1', 99);
        $variantValue = $request->body()['variant_id'] ?? '';
        $variantId = $variantValue === '' ? null : self::positiveInteger($variantValue, PHP_INT_MAX);
        if ($productId === null || $quantity === null || ($variantValue !== '' && $variantId === null)) {
            return new Response(400, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Choose a valid product quantity.');
        }

        try {
            $this->repository()->addProduct($this->cartKey(), $productId, $quantity, $variantId);
        } catch (CartException $exception) {
            $_SESSION['cart_error'] = $exception->getMessage();
            return $this->redirect('/cart');
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }

        $_SESSION['cart_notice'] = 'Product added to your cart.';

        return $this->redirect('/cart');
    }

    public function updateItem(array $params, Request $request): Response
    {
        return $this->changeItem($params, $request, false);
    }

    public function removeItem(array $params, Request $request): Response
    {
        return $this->changeItem($params, $request, true);
    }

    public static function positiveInteger(mixed $value, int $maximum): ?int
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }

        $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $maximum]]);

        return $validated === false ? null : $validated;
    }

    private function changeItem(array $params, Request $request, bool $remove): Response
    {
        SessionSecurity::start();
        if (!SessionSecurity::verifyCsrfToken($request->body()['csrf_token'] ?? null)) {
            return new Response(400, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Your form session expired. Please try again.');
        }

        $itemId = self::positiveInteger($params['id'] ?? null, PHP_INT_MAX);
        $quantity = $remove ? 1 : self::positiveInteger($request->body()['quantity'] ?? null, 99);
        if ($itemId === null || $quantity === null) {
            return new Response(400, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Choose a valid cart quantity.');
        }

        try {
            $repository = $this->repository();
            $changed = $remove
                ? $repository->removeItem($this->cartKey(), $itemId)
                : $repository->updateQuantity($this->cartKey(), $itemId, $quantity);
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }

        if (!$changed) {
            $_SESSION['cart_error'] = 'That cart item is no longer available at the requested quantity.';
        } else {
            $_SESSION['cart_notice'] = $remove ? 'Product removed from your cart.' : 'Cart updated.';
        }

        return $this->redirect('/cart');
    }

    private function repository(): CartRepository
    {
        return new CartRepository(Database::connect($this->databaseConfig));
    }

    private function cartKey(): string
    {
        if (!isset($_SESSION['cart_key'])) {
            $_SESSION['cart_key'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['cart_key'];
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
        error_log('Cart request failed: ' . $exception->getMessage());

        return new Response(503, ['Content-Type' => 'text/html; charset=UTF-8'], 'Your cart is temporarily unavailable.');
    }
}
