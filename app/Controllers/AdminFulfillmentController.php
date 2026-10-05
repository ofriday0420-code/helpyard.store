<?php

namespace Helpyard\App\Controllers;

use Helpyard\App\Core\Database;
use Helpyard\App\Core\FulfillmentException;
use Helpyard\App\Core\Request;
use Helpyard\App\Core\Response;
use Helpyard\App\Core\SessionSecurity;
use Helpyard\App\Repositories\FulfillmentRepository;
use Helpyard\App\Services\FulfillmentPolicy;
use PDOException;
use RuntimeException;

class AdminFulfillmentController
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
            $orders = (new FulfillmentRepository(Database::connect($this->databaseConfig)))->queue();
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }

        $title = 'Physical order fulfillment';
        $description = 'Process, ship, and track eligible customer orders.';
        $csrfToken = SessionSecurity::csrfToken();
        $notice = $this->consumeFlash('fulfillment_notice');
        $error = $this->consumeFlash('fulfillment_error');
        ob_start();
        require __DIR__ . '/../Views/admin/fulfillment.php';
        $html = ob_get_clean();
        if ($html === false) {
            throw new RuntimeException('Could not render the fulfillment queue.');
        }

        return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $html);
    }

    public function advance(array $params, Request $request): Response
    {
        SessionSecurity::start();
        if (!$this->isAdmin()) {
            return $this->forbiddenOrLogin();
        }
        if (!SessionSecurity::verifyCsrfToken($request->body()['csrf_token'] ?? null)) {
            return new Response(400, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Your form session expired. Please try again.');
        }

        $orderId = CartController::positiveInteger($params['id'] ?? null, PHP_INT_MAX);
        $body = $request->body();
        $action = is_string($body['action'] ?? null) ? $body['action'] : '';
        if ($orderId === null || FulfillmentPolicy::nextStatus('paid', $action) === null
            && FulfillmentPolicy::nextStatus('processing', $action) === null
            && FulfillmentPolicy::nextStatus('shipped', $action) === null
        ) {
            $_SESSION['fulfillment_error'] = 'Choose a valid fulfillment action.';
            return $this->redirect('/admin/fulfillment');
        }

        $carrier = null;
        $trackingNumber = null;
        if ($action === 'mark_shipped') {
            $details = FulfillmentPolicy::validateShipmentDetails(
                is_string($body['carrier'] ?? null) ? $body['carrier'] : '',
                is_string($body['tracking_number'] ?? null) ? $body['tracking_number'] : ''
            );
            if ($details['error'] !== null) {
                $_SESSION['fulfillment_error'] = $details['error'];
                return $this->redirect('/admin/fulfillment');
            }
            $carrier = $details['carrier'];
            $trackingNumber = $details['tracking_number'];
        }

        try {
            (new FulfillmentRepository(Database::connect($this->databaseConfig)))->advance(
                $orderId,
                (int) $_SESSION['user_id'],
                $action,
                $carrier,
                $trackingNumber
            );
        } catch (FulfillmentException $exception) {
            $_SESSION['fulfillment_error'] = $exception->getMessage();
            return $this->redirect('/admin/fulfillment');
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }

        $_SESSION['fulfillment_notice'] = 'Order fulfillment status updated.';

        return $this->redirect('/admin/fulfillment');
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
        error_log('Fulfillment request failed: ' . $exception->getMessage());

        return new Response(503, ['Content-Type' => 'text/html; charset=UTF-8'], 'Fulfillment is temporarily unavailable.');
    }
}
