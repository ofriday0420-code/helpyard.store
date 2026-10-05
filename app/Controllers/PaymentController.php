<?php

namespace Helpyard\App\Controllers;

use Helpyard\App\Core\Database;
use Helpyard\App\Core\PaymentException;
use Helpyard\App\Core\Request;
use Helpyard\App\Core\Response;
use Helpyard\App\Core\SessionSecurity;
use Helpyard\App\Repositories\OrderRepository;
use Helpyard\App\Repositories\PaymentRepository;
use Helpyard\App\Services\SSLCommerzGateway;
use PDOException;
use RuntimeException;

class PaymentController
{
    public function __construct(
        private array $databaseConfig,
        private array $paymentConfig
    ) {
    }

    public function initiate(array $params, Request $request): Response
    {
        SessionSecurity::start();
        if (!$this->authenticated()) {
            return $this->redirect('/login');
        }
        if (!SessionSecurity::verifyCsrfToken($request->body()['csrf_token'] ?? null)) {
            return new Response(400, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Your form session expired. Please try again.');
        }

        $orderId = CartController::positiveInteger($params['id'] ?? null, PHP_INT_MAX);
        if ($orderId === null) {
            return $this->notFound();
        }
        $gateway = new SSLCommerzGateway($this->paymentConfig);
        if (!$gateway->enabled()) {
            $_SESSION['payment_error'] = 'Online payment is not configured. No payment has been taken.';
            return $this->redirect('/orders/' . $orderId);
        }

        $repository = null;
        $transactionId = null;
        try {
            $connection = Database::connect($this->databaseConfig);
            $orders = new OrderRepository($connection);
            $orders->releaseExpiredReservations();
            $repository = new PaymentRepository($connection);
            $payment = $repository->prepareAttempt($orderId, (int) $_SESSION['user_id']);
            if (isset($payment['redirect_url'])) {
                return new Response(303, ['Location' => $payment['redirect_url']], '');
            }
            $transactionId = $payment['transaction_id'];
            $session = $gateway->initiate($payment);
            $repository->saveGatewaySession(
                $transactionId,
                $session['session_key'],
                $session['redirect_url']
            );
        } catch (PaymentException $exception) {
            if ($repository !== null && $transactionId !== null) {
                try {
                    $repository->markInitiationFailed($transactionId);
                } catch (PDOException | RuntimeException $failureException) {
                    error_log('Could not record failed payment initiation: ' . $failureException->getMessage());
                }
            }
            $_SESSION['payment_error'] = $exception->getMessage();
            return $this->redirect('/orders/' . $orderId);
        } catch (PDOException | RuntimeException $exception) {
            if ($repository !== null && $transactionId !== null) {
                try {
                    $repository->markInitiationFailed($transactionId);
                } catch (RuntimeException $failureException) {
                    error_log('Could not record failed payment initiation: ' . $failureException->getMessage());
                }
            }
            return $this->unavailable($exception);
        }

        return new Response(303, ['Location' => $session['redirect_url']], '');
    }

    public function ipn(array $params, Request $request): Response
    {
        $body = $request->body();
        $validationId = $body['val_id'] ?? null;
        $transactionId = $body['tran_id'] ?? null;
        if (!is_string($validationId) || $validationId === '' || strlen($validationId) > 100
            || !is_string($transactionId) || !preg_match('/^HY[A-F0-9]{24}$/', $transactionId)
        ) {
            return new Response(400, ['Content-Type' => 'application/json; charset=UTF-8'], [
                'success' => false,
                'error' => 'Invalid payment notification.',
            ]);
        }

        try {
            $gateway = new SSLCommerzGateway($this->paymentConfig);
            $validated = $gateway->validate($validationId);
            if (($validated['tran_id'] ?? null) !== $transactionId
                || ($validated['val_id'] ?? null) !== $validationId
            ) {
                throw new PaymentException('Payment validation references do not match.');
            }

            $connection = Database::connect($this->databaseConfig);
            (new OrderRepository($connection))->releaseExpiredReservations();
            $result = (new PaymentRepository($connection))->confirmValidatedPayment($validated);

            return new Response(200, ['Content-Type' => 'application/json; charset=UTF-8'], [
                'success' => true,
                'order_id' => $result['order_id'],
            ]);
        } catch (PaymentException $exception) {
            error_log('Payment notification was rejected: ' . $exception->getMessage());
            return new Response(400, ['Content-Type' => 'application/json; charset=UTF-8'], [
                'success' => false,
                'error' => 'Payment notification could not be verified.',
            ]);
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }
    }

    public function browserReturn(array $params, Request $request): Response
    {
        SessionSecurity::start();
        $result = $params['result'] ?? '';
        if (!in_array($result, ['success', 'fail', 'cancel'], true)) {
            return new Response(404, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Payment return not found.');
        }
        $body = $request->body();
        $validationId = $body['val_id'] ?? $request->query()['val_id'] ?? null;
        $transactionId = $body['tran_id'] ?? $request->query()['tran_id'] ?? null;

        if ($result === 'success' && is_string($validationId) && $validationId !== ''
            && is_string($transactionId) && preg_match('/^HY[A-F0-9]{24}$/', $transactionId)
        ) {
            try {
                $validated = (new SSLCommerzGateway($this->paymentConfig))->validate($validationId);
                if (($validated['tran_id'] ?? null) === $transactionId
                    && ($validated['val_id'] ?? null) === $validationId
                ) {
                    $connection = Database::connect($this->databaseConfig);
                    (new OrderRepository($connection))->releaseExpiredReservations();
                    (new PaymentRepository($connection))->confirmValidatedPayment($validated);
                }
            } catch (PaymentException $exception) {
                error_log('Payment return was not confirmed: ' . $exception->getMessage());
            } catch (PDOException | RuntimeException $exception) {
                return $this->unavailable($exception);
            }
        }

        if ($this->authenticated() && is_string($transactionId)
            && preg_match('/^HY[A-F0-9]{24}$/', $transactionId)
        ) {
            try {
                $payments = new PaymentRepository(Database::connect($this->databaseConfig));
                $orderId = $payments->findOrderIdForTransaction($transactionId, (int) $_SESSION['user_id']);
            } catch (PDOException | RuntimeException $exception) {
                return $this->unavailable($exception);
            }
            if ($orderId !== null) {
                return $this->redirect('/orders/' . $orderId);
            }
        }

        $message = $result === 'success'
            ? 'Payment is being verified. Sign in to view your order status.'
            : 'Payment was not completed. Your order may still be available for another attempt.';

        return new Response(200, ['Content-Type' => 'text/plain; charset=UTF-8'], $message);
    }

    private function authenticated(): bool
    {
        return isset($_SESSION['user_id'], $_SESSION['user_role'])
            && $_SESSION['user_role'] === 'customer';
    }

    private function redirect(string $location): Response
    {
        return new Response(303, ['Location' => $location], '');
    }

    private function notFound(): Response
    {
        return new Response(404, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Order not found.');
    }

    private function unavailable(PDOException | RuntimeException $exception): Response
    {
        error_log('Payment request failed: ' . $exception->getMessage());

        return new Response(503, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Payment service is temporarily unavailable.');
    }
}
