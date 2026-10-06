<?php

namespace Helpyard\App\Controllers;

use Helpyard\App\Core\CartException;
use Helpyard\App\Core\Database;
use Helpyard\App\Core\Request;
use Helpyard\App\Core\Response;
use Helpyard\App\Repositories\CartRepository;
use Helpyard\App\Repositories\CourseRepository;
use Helpyard\App\Repositories\CustomerApiTokenRepository;
use Helpyard\App\Repositories\OrderRepository;
use Helpyard\App\Repositories\UserRepository;
use PDO;
use Throwable;

class CustomerApiController
{
    public function __construct(private array $databaseConfig)
    {
    }

    public function issueToken(array $params, Request $request): Response
    {
        if (!$this->validJsonRequest($request)) {
            return $this->error(400, 'Send a valid JSON object.');
        }

        $body = $request->body();
        $email = is_string($body['email'] ?? null) ? strtolower(trim($body['email'])) : '';
        $password = is_string($body['password'] ?? null) ? $body['password'] : '';
        $nameValue = $body['device_name'] ?? 'Customer API';
        $name = is_string($nameValue) ? trim($nameValue) : '';
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 180
            || $password === '' || strlen($password) > 72 || $name === '' || strlen($name) > 80
        ) {
            return $this->error(400, 'Provide a valid email, password, and device name (up to 80 characters).');
        }

        try {
            $connection = Database::connect($this->databaseConfig);
            $users = new UserRepository($connection);
            $identifierHash = hash('sha256', $email . '|' . $request->remoteAddress());
            $users->deleteExpiredLoginAttempts(gmdate('Y-m-d H:i:s', time() - 86400));
            if ($users->loginAttempts($identifierHash, gmdate('Y-m-d H:i:s', time() - 900)) >= 10) {
                return $this->error(429, 'Sign-in is temporarily unavailable. Try again later.');
            }

            $user = $users->findByEmail($email);
            if ($user === null || !password_verify($password, $user['password_hash']) || $user['role'] !== 'customer') {
                $users->recordLoginAttempt($identifierHash);
                return $this->error(401, 'The email or password is incorrect.');
            }
            $users->clearLoginAttempts($identifierHash);

            $issued = (new CustomerApiTokenRepository($connection))->issue((int) $user['id'], $name);
        } catch (Throwable $exception) {
            return $this->unavailable($exception);
        }

        return $this->json(201, [
            'success' => true,
            'token_type' => 'Bearer',
            'access_token' => $issued['token'],
            'expires_at' => $issued['expires_at'],
            'customer' => [
                'id' => (int) $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
            ],
        ]);
    }

    public function revokeToken(array $params, Request $request): Response
    {
        return $this->withCustomer($request, static function (PDO $connection, array $customer): Response {
            (new CustomerApiTokenRepository($connection))->revoke(
                (int) $customer['token_id'],
                (int) $customer['user_id']
            );

            return new Response(204, ['Cache-Control' => 'no-store'], '');
        });
    }

    public function tokens(array $params, Request $request): Response
    {
        return $this->withCustomer($request, static function (PDO $connection, array $customer): Response {
            $tokens = (new CustomerApiTokenRepository($connection))->forCustomer((int) $customer['user_id']);

            return self::json(200, ['success' => true, 'tokens' => $tokens]);
        });
    }

    public function revokeNamedToken(array $params, Request $request): Response
    {
        return $this->withCustomer($request, static function (PDO $connection, array $customer) use ($params): Response {
            $tokenId = CartController::positiveInteger($params['id'] ?? null, PHP_INT_MAX);
            if ($tokenId === null || !(new CustomerApiTokenRepository($connection))->revoke(
                $tokenId,
                (int) $customer['user_id']
            )) {
                return self::error(404, 'Active API token not found.');
            }

            return new Response(204, ['Cache-Control' => 'no-store'], '');
        });
    }

    public function account(array $params, Request $request): Response
    {
        return $this->withCustomer($request, static function (PDO $connection, array $customer): Response {
            return self::json(200, [
                'success' => true,
                'customer' => [
                    'id' => (int) $customer['user_id'],
                    'name' => $customer['name'],
                    'email' => $customer['email'],
                ],
            ]);
        });
    }

    public function orders(array $params, Request $request): Response
    {
        return $this->withCustomer($request, static function (PDO $connection, array $customer) use ($request): Response {
            $limit = $request->query()['limit'] ?? '50';
            $validatedLimit = filter_var($limit, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
            if ($validatedLimit === false) {
                return self::error(400, 'Order limit must be between 1 and 100.');
            }

            $orders = (new OrderRepository($connection))->forCustomer((int) $customer['user_id'], $validatedLimit);

            return self::json(200, ['success' => true, 'orders' => $orders]);
        });
    }

    public function order(array $params, Request $request): Response
    {
        return $this->withCustomer($request, static function (PDO $connection, array $customer) use ($params): Response {
            $orderId = CartController::positiveInteger($params['id'] ?? null, PHP_INT_MAX);
            if ($orderId === null) {
                return self::error(404, 'Order not found.');
            }
            $order = (new OrderRepository($connection))->findForCustomer($orderId, (int) $customer['user_id']);
            if ($order === null) {
                return self::error(404, 'Order not found.');
            }

            return self::json(200, ['success' => true, 'order' => $order]);
        });
    }

    public function courses(array $params, Request $request): Response
    {
        return $this->withCustomer($request, static function (PDO $connection, array $customer): Response {
            $courses = (new CourseRepository($connection))->forCustomer((int) $customer['user_id']);

            return self::json(200, ['success' => true, 'courses' => $courses]);
        });
    }

    public function course(array $params, Request $request): Response
    {
        return $this->withCustomer($request, static function (PDO $connection, array $customer) use ($params): Response {
            $courseId = CartController::positiveInteger($params['id'] ?? null, PHP_INT_MAX);
            if ($courseId === null) {
                return self::error(404, 'Course not found.');
            }
            $course = (new CourseRepository($connection))->findForCustomer($courseId, (int) $customer['user_id']);
            if ($course === null) {
                return self::error(404, 'Course not found.');
            }

            return self::json(200, ['success' => true, 'course' => $course]);
        });
    }

    public function completeLesson(array $params, Request $request): Response
    {
        return $this->withCustomer($request, static function (PDO $connection, array $customer) use ($params): Response {
            $courseId = CartController::positiveInteger($params['id'] ?? null, PHP_INT_MAX);
            $lessonId = CartController::positiveInteger($params['lesson_id'] ?? null, PHP_INT_MAX);
            if ($courseId === null || $lessonId === null) {
                return self::error(404, 'Course lesson not found.');
            }
            $completed = (new CourseRepository($connection))->completeLesson(
                $courseId,
                $lessonId,
                (int) $customer['user_id']
            );
            if (!$completed) {
                return self::error(404, 'Course lesson not found.');
            }

            return self::json(200, ['success' => true, 'completed' => true]);
        });
    }

    public function cart(array $params, Request $request): Response
    {
        return $this->withCustomer($request, static function (PDO $connection, array $customer): Response {
            $cart = (new CartRepository($connection))->contents(self::cartKey((int) $customer['user_id']));

            return self::json(200, ['success' => true, 'cart' => $cart]);
        });
    }

    public function addCartItem(array $params, Request $request): Response
    {
        if (!$this->validJsonRequest($request)) {
            return $this->error(400, 'Send a valid JSON object.');
        }

        return $this->withCustomer($request, static function (PDO $connection, array $customer) use ($request): Response {
            $body = $request->body();
            $productId = CartController::positiveInteger($body['product_id'] ?? null, PHP_INT_MAX);
            $quantity = CartController::positiveInteger($body['quantity'] ?? 1, 99);
            $variantValue = $body['variant_id'] ?? null;
            $variantId = $variantValue === null || $variantValue === ''
                ? null
                : CartController::positiveInteger($variantValue, PHP_INT_MAX);
            if ($productId === null || $quantity === null || ($variantValue !== null && $variantValue !== '' && $variantId === null)) {
                return self::error(400, 'Provide a valid product, quantity, and optional variant.');
            }

            (new CartRepository($connection))->addProduct(
                self::cartKey((int) $customer['user_id']),
                $productId,
                $quantity,
                $variantId
            );
            $cart = (new CartRepository($connection))->contents(self::cartKey((int) $customer['user_id']));

            return self::json(200, ['success' => true, 'cart' => $cart]);
        });
    }

    public function updateCartItem(array $params, Request $request): Response
    {
        if (!$this->validJsonRequest($request)) {
            return $this->error(400, 'Send a valid JSON object.');
        }

        return $this->withCustomer($request, static function (PDO $connection, array $customer) use ($params, $request): Response {
            $itemId = CartController::positiveInteger($params['id'] ?? null, PHP_INT_MAX);
            $quantity = CartController::positiveInteger($request->body()['quantity'] ?? null, 99);
            if ($itemId === null || $quantity === null) {
                return self::error(400, 'Provide a valid cart item and quantity.');
            }

            $repository = new CartRepository($connection);
            if (!$repository->updateQuantity(self::cartKey((int) $customer['user_id']), $itemId, $quantity)) {
                return self::error(409, 'That cart item is no longer available at the requested quantity.');
            }

            return self::json(200, [
                'success' => true,
                'cart' => $repository->contents(self::cartKey((int) $customer['user_id'])),
            ]);
        });
    }

    public function removeCartItem(array $params, Request $request): Response
    {
        return $this->withCustomer($request, static function (PDO $connection, array $customer) use ($params): Response {
            $itemId = CartController::positiveInteger($params['id'] ?? null, PHP_INT_MAX);
            if ($itemId === null) {
                return self::error(404, 'Cart item not found.');
            }

            $repository = new CartRepository($connection);
            if (!$repository->removeItem(self::cartKey((int) $customer['user_id']), $itemId)) {
                return self::error(404, 'Cart item not found.');
            }

            return self::json(200, [
                'success' => true,
                'cart' => $repository->contents(self::cartKey((int) $customer['user_id'])),
            ]);
        });
    }

    private function withCustomer(Request $request, callable $action): Response
    {
        $authorization = $request->header('Authorization') ?? '';
        if (preg_match('/^Bearer ([a-f0-9]{64})$/i', $authorization, $matches) !== 1) {
            return $this->unauthorized();
        }

        try {
            $connection = Database::connect($this->databaseConfig);
            $customer = (new CustomerApiTokenRepository($connection))->customerForToken(strtolower($matches[1]));
            if ($customer === null) {
                return $this->unauthorized();
            }

            return $action($connection, $customer);
        } catch (CartException $exception) {
            return $this->error(409, $exception->getMessage());
        } catch (Throwable $exception) {
            return $this->unavailable($exception);
        }
    }

    private function validJsonRequest(Request $request): bool
    {
        return $request->hasJsonContentType() && $request->jsonBodyValid();
    }

    private static function cartKey(int $userId): string
    {
        return 'account-' . $userId;
    }

    private function unauthorized(): Response
    {
        return new Response(401, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'WWW-Authenticate' => 'Bearer',
        ], ['success' => false, 'error' => 'A valid customer bearer token is required.']);
    }

    private function unavailable(Throwable $exception): Response
    {
        error_log('Customer API request failed: ' . $exception->getMessage());

        return $this->error(503, 'The customer API is temporarily unavailable.');
    }

    private function error(int $status, string $message): Response
    {
        return self::json($status, ['success' => false, 'error' => $message]);
    }

    private static function json(int $status, array $body): Response
    {
        return new Response($status, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], $body);
    }
}
