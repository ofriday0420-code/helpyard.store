<?php

namespace Helpyard\App\Controllers;

use Helpyard\App\Core\Database;
use Helpyard\App\Core\Request;
use Helpyard\App\Core\Response;
use Helpyard\App\Core\SessionSecurity;
use Helpyard\App\Repositories\AdminOrderRepository;
use PDOException;
use RuntimeException;

class AdminOrderController
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
            $orders = (new AdminOrderRepository(Database::connect($this->databaseConfig)))->queue();
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }

        $title = 'Order administration';
        $description = 'Review customer orders and verified payment records.';
        ob_start();
        require __DIR__ . '/../Views/admin/orders.php';
        $html = ob_get_clean();
        if ($html === false) {
            throw new RuntimeException('Could not render the order administration queue.');
        }

        return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $html);
    }

    public function show(array $params, ?Request $request = null): Response
    {
        SessionSecurity::start();
        if (!$this->isAdmin()) {
            return $this->forbiddenOrLogin();
        }

        $orderId = filter_var($params['id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX],
        ]);
        if ($orderId === false) {
            return new Response(404, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Order not found.');
        }

        try {
            $order = (new AdminOrderRepository(Database::connect($this->databaseConfig)))->find((int) $orderId);
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }
        if ($order === null) {
            return new Response(404, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Order not found.');
        }

        $title = 'Order ' . $order['order_number'];
        $description = 'Review order details and payment validation records.';
        $csrfToken = SessionSecurity::csrfToken();
        $notice = $this->consumeFlash('admin_order_notice');
        $error = $this->consumeFlash('admin_order_error');
        ob_start();
        require __DIR__ . '/../Views/admin/order.php';
        $html = ob_get_clean();
        if ($html === false) {
            throw new RuntimeException('Could not render the order administration detail.');
        }

        return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $html);
    }

    public function addNote(array $params, Request $request): Response
    {
        SessionSecurity::start();
        if (!$this->isAdmin()) {
            return $this->forbiddenOrLogin();
        }
        if (!SessionSecurity::verifyCsrfToken($request->body()['csrf_token'] ?? null)) {
            return new Response(400, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Your form session expired. Please try again.');
        }

        $orderId = filter_var($params['id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX],
        ]);
        if ($orderId === false) {
            return new Response(404, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Order not found.');
        }

        $note = self::validateReviewNote($request->body()['note'] ?? null);
        if ($note === null) {
            $_SESSION['admin_order_error'] = 'Enter a review note between 3 and 2,000 characters.';
            return new Response(303, ['Location' => '/admin/orders/' . $orderId], '');
        }

        try {
            $added = (new AdminOrderRepository(Database::connect($this->databaseConfig)))
                ->addReviewNote((int) $orderId, (int) $_SESSION['user_id'], $note);
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }
        if (!$added) {
            return new Response(404, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Order not found.');
        }

        $_SESSION['admin_order_notice'] = 'Review note recorded.';
        return new Response(303, ['Location' => '/admin/orders/' . $orderId], '');
    }

    public static function validateReviewNote(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        $length = preg_match_all('/./us', $value);
        if ($length === false || $length < 3 || $length > 2000
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)
        ) {
            return null;
        }

        return $value;
    }

    private function isAdmin(): bool
    {
        return isset($_SESSION['user_id'], $_SESSION['user_role'])
            && $_SESSION['user_role'] === 'admin';
    }

    private function forbiddenOrLogin(): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return new Response(303, ['Location' => '/login'], '');
        }

        return new Response(403, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Administrator access is required.');
    }

    private function consumeFlash(string $key): string
    {
        $value = $_SESSION[$key] ?? '';
        unset($_SESSION[$key]);

        return is_string($value) ? $value : '';
    }

    private function unavailable(PDOException | RuntimeException $exception): Response
    {
        error_log('Order administration request failed: ' . $exception->getMessage());

        return new Response(503, ['Content-Type' => 'text/html; charset=UTF-8'], 'Order administration is temporarily unavailable.');
    }
}
