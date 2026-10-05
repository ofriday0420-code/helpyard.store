<?php

namespace Helpyard\App\Controllers;

use Helpyard\App\Core\CheckoutException;
use Helpyard\App\Core\Database;
use Helpyard\App\Core\Request;
use Helpyard\App\Core\Response;
use Helpyard\App\Core\SessionSecurity;
use Helpyard\App\Repositories\CartRepository;
use Helpyard\App\Repositories\OrderRepository;
use Helpyard\App\Repositories\UserRepository;
use PDOException;
use RuntimeException;

class CheckoutController
{
    public function __construct(
        private array $databaseConfig,
        private array $paymentConfig = []
    ) {
    }

    public function show(array $params = [], ?Request $request = null): Response
    {
        SessionSecurity::start();
        if (!$this->authenticated()) {
            return $this->redirect('/login');
        }

        try {
            $connection = Database::connect($this->databaseConfig);
            (new OrderRepository($connection))->releaseExpiredReservations();
            $userId = (int) $_SESSION['user_id'];
            $cartKey = $this->cartKey();
            $cart = (new CartRepository($connection))->contents($cartKey);
            $addresses = (new UserRepository($connection))->addressesForCustomer($userId);
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }

        $canSubmit = $cart['items'] !== [] && $addresses !== [];
        foreach ($cart['items'] as $item) {
            if ((int) $item['is_available'] !== 1 || (int) $item['quantity'] > (int) $item['stock_quantity']) {
                $canSubmit = false;
                break;
            }
        }

        $title = 'Checkout';
        $description = 'Confirm your saved delivery address and review your order.';
        $csrfToken = SessionSecurity::csrfToken();
        $error = $this->consumeFlash('checkout_error');
        ob_start();
        require __DIR__ . '/../Views/checkout.php';
        $html = ob_get_clean();
        if ($html === false) {
            throw new RuntimeException('Could not render checkout.');
        }

        return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $html);
    }

    public function createOrder(array $params, Request $request): Response
    {
        SessionSecurity::start();
        if (!$this->authenticated()) {
            return $this->redirect('/login');
        }
        if (!SessionSecurity::verifyCsrfToken($request->body()['csrf_token'] ?? null)) {
            return new Response(400, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Your form session expired. Please try again.');
        }

        $addressId = CartController::positiveInteger($request->body()['address_id'] ?? null, PHP_INT_MAX);
        if ($addressId === null) {
            $_SESSION['checkout_error'] = 'Choose a valid saved delivery address.';
            return $this->redirect('/checkout');
        }

        try {
            $order = (new OrderRepository(Database::connect($this->databaseConfig)))->createFromCart(
                $this->cartKey(),
                (int) $_SESSION['user_id'],
                $addressId
            );
        } catch (CheckoutException $exception) {
            $_SESSION['checkout_error'] = $exception->getMessage();
            return $this->redirect('/checkout');
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }

        return $this->redirect('/orders/' . $order['id']);
    }

    public function showOrder(array $params, ?Request $request = null): Response
    {
        SessionSecurity::start();
        if (!$this->authenticated()) {
            return $this->redirect('/login');
        }

        $orderId = CartController::positiveInteger($params['id'] ?? null, PHP_INT_MAX);
        if ($orderId === null) {
            return $this->notFound();
        }

        try {
            $repository = new OrderRepository(Database::connect($this->databaseConfig));
            $repository->releaseExpiredReservations();
            $order = $repository->findForCustomer($orderId, (int) $_SESSION['user_id']);
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }
        if ($order === null) {
            return $this->notFound();
        }

        $title = 'Order ' . $order['order_number'];
        $description = 'View the status and details of your Helpyard.store order.';
        $paymentEnabled = ($this->paymentConfig['enabled'] ?? false) === true;
        $csrfToken = SessionSecurity::csrfToken();
        $paymentError = $this->consumeFlash('payment_error');
        ob_start();
        require __DIR__ . '/../Views/order.php';
        $html = ob_get_clean();
        if ($html === false) {
            throw new RuntimeException('Could not render the order details.');
        }

        return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $html);
    }

    private function authenticated(): bool
    {
        return isset($_SESSION['user_id'], $_SESSION['user_role'])
            && $_SESSION['user_role'] === 'customer';
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

    private function notFound(): Response
    {
        return new Response(404, ['Content-Type' => 'text/html; charset=UTF-8'], 'Order not found.');
    }

    private function unavailable(PDOException | RuntimeException $exception): Response
    {
        error_log('Checkout request failed: ' . $exception->getMessage());

        return new Response(503, ['Content-Type' => 'text/html; charset=UTF-8'], 'Checkout is temporarily unavailable.');
    }
}
