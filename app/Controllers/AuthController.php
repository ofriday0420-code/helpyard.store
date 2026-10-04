<?php

namespace Helpyard\App\Controllers;

use Helpyard\App\Core\Database;
use Helpyard\App\Core\Request;
use Helpyard\App\Core\Response;
use Helpyard\App\Core\SessionSecurity;
use Helpyard\App\Repositories\UserRepository;
use PDOException;
use RuntimeException;

class AuthController
{
    public function __construct(private array $databaseConfig)
    {
    }

    public function registerForm(array $params = []): Response
    {
        SessionSecurity::start();
        if ($this->authenticated()) {
            return $this->redirect('/account');
        }

        return $this->render('auth/register.php', [
            'csrfToken' => SessionSecurity::csrfToken(),
            'error' => $this->consumeFlash('auth_error'),
            'oldName' => $this->consumeFlash('auth_name'),
            'oldEmail' => $this->consumeFlash('auth_email'),
        ]);
    }

    public function register(array $params, Request $request): Response
    {
        SessionSecurity::start();
        if (!SessionSecurity::verifyCsrfToken($request->body()['csrf_token'] ?? null)) {
            return $this->render('auth/register.php', [
                'csrfToken' => SessionSecurity::csrfToken(),
                'error' => 'Your form session expired. Please try again.',
                'oldName' => '',
                'oldEmail' => '',
            ], 400);
        }

        $body = $request->body();
        $name = is_string($body['name'] ?? null) ? trim($body['name']) : '';
        $email = is_string($body['email'] ?? null) ? strtolower(trim($body['email'])) : '';
        $password = is_string($body['password'] ?? null) ? $body['password'] : '';
        $errors = self::validateRegistration($name, $email, $password);
        if ($errors !== []) {
            $_SESSION['auth_error'] = implode(' ', $errors);
            $_SESSION['auth_name'] = $name;
            $_SESSION['auth_email'] = $email;

            return $this->redirect('/register');
        }

        try {
            $users = new UserRepository(Database::connect($this->databaseConfig));
            $users->createCustomer($name, $email, password_hash($password, PASSWORD_DEFAULT));
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $_SESSION['auth_error'] = 'An account could not be created with those details. Check the email address or try signing in.';
                $_SESSION['auth_name'] = $name;
                $_SESSION['auth_email'] = $email;

                return $this->redirect('/register');
            }
            return $this->unavailable($exception);
        } catch (RuntimeException $exception) {
            return $this->unavailable($exception);
        }

        $_SESSION['auth_notice'] = 'Your account is ready. You can now sign in.';

        return $this->redirect('/login');
    }

    public function loginForm(array $params = []): Response
    {
        SessionSecurity::start();
        if ($this->authenticated()) {
            return $this->redirect('/account');
        }

        return $this->render('auth/login.php', [
            'csrfToken' => SessionSecurity::csrfToken(),
            'error' => $this->consumeFlash('auth_error'),
            'notice' => $this->consumeFlash('auth_notice'),
            'oldEmail' => $this->consumeFlash('auth_email'),
        ]);
    }

    public function login(array $params, Request $request): Response
    {
        SessionSecurity::start();
        if (!SessionSecurity::verifyCsrfToken($request->body()['csrf_token'] ?? null)) {
            return $this->render('auth/login.php', [
                'csrfToken' => SessionSecurity::csrfToken(),
                'error' => 'Your form session expired. Please try again.',
                'notice' => '',
                'oldEmail' => '',
            ], 400);
        }

        $body = $request->body();
        $email = is_string($body['email'] ?? null) ? strtolower(trim($body['email'])) : '';
        $password = is_string($body['password'] ?? null) ? $body['password'] : '';
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 180 || $password === '' || strlen($password) > 72) {
            return $this->loginFailed($email);
        }

        $identifierHash = hash('sha256', strtolower($email) . '|' . $request->remoteAddress());

        try {
            $users = new UserRepository(Database::connect($this->databaseConfig));
            $users->deleteExpiredLoginAttempts(gmdate('Y-m-d H:i:s', time() - 86400));
            if ($users->loginAttempts($identifierHash, gmdate('Y-m-d H:i:s', time() - 900)) >= 10) {
                return $this->loginFailed($email);
            }

            $user = $users->findByEmail($email);
            if ($user === null || !password_verify($password, $user['password_hash']) || $user['role'] !== 'customer') {
                $users->recordLoginAttempt($identifierHash);

                return $this->loginFailed($email);
            }

            $users->clearLoginAttempts($identifierHash);
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }

        if (!session_regenerate_id(true)) {
            error_log('Could not regenerate the session ID after login.');
            return new Response(500, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Could not establish a secure session.');
        }

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = 'customer';
        unset($_SESSION['csrf_token']);

        return $this->redirect('/account');
    }

    public function logout(array $params, Request $request): Response
    {
        SessionSecurity::start();
        if (!SessionSecurity::verifyCsrfToken($request->body()['csrf_token'] ?? null)) {
            return new Response(400, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Your form session expired. Please try again.');
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $parameters['path'],
                'domain' => $parameters['domain'],
                'secure' => $parameters['secure'],
                'httponly' => $parameters['httponly'],
                'samesite' => $parameters['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();

        return $this->redirect('/');
    }

    public function account(array $params = []): Response
    {
        SessionSecurity::start();
        if (!$this->authenticated()) {
            return $this->redirect('/login');
        }

        try {
            $user = (new UserRepository(Database::connect($this->databaseConfig)))
                ->findCustomerById((int) $_SESSION['user_id']);
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }

        if ($user === null) {
            $_SESSION = [];
            session_regenerate_id(true);

            return $this->redirect('/login');
        }

        return $this->render('auth/account.php', [
            'user' => $user,
            'csrfToken' => SessionSecurity::csrfToken(),
            'notice' => $this->consumeFlash('account_notice'),
            'error' => $this->consumeFlash('account_error'),
        ]);
    }

    public function updateProfile(array $params, Request $request): Response
    {
        SessionSecurity::start();
        if (!$this->authenticated()) {
            return $this->redirect('/login');
        }
        if (!SessionSecurity::verifyCsrfToken($request->body()['csrf_token'] ?? null)) {
            return new Response(400, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Your form session expired. Please try again.');
        }

        $name = is_string($request->body()['name'] ?? null) ? trim($request->body()['name']) : '';
        $nameLength = preg_match_all('/./us', $name);
        if ($name === '' || $nameLength === false || $nameLength > 120 || preg_match('/[\x00-\x1F\x7F]/', $name)) {
            $_SESSION['account_error'] = 'Enter a name up to 120 characters.';
            return $this->redirect('/account');
        }

        try {
            (new UserRepository(Database::connect($this->databaseConfig)))
                ->updateCustomerName((int) $_SESSION['user_id'], $name);
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }

        $_SESSION['user_name'] = $name;
        $_SESSION['account_notice'] = 'Your profile has been updated.';

        return $this->redirect('/account');
    }

    public function addresses(array $params = []): Response
    {
        SessionSecurity::start();
        if (!$this->authenticated()) {
            return $this->redirect('/login');
        }

        try {
            $addresses = (new UserRepository(Database::connect($this->databaseConfig)))
                ->addressesForCustomer((int) $_SESSION['user_id']);
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }

        return $this->render('auth/addresses.php', [
            'addresses' => $addresses,
            'csrfToken' => SessionSecurity::csrfToken(),
            'error' => $this->consumeFlash('address_error'),
            'notice' => $this->consumeFlash('address_notice'),
        ]);
    }

    public function addAddress(array $params, Request $request): Response
    {
        SessionSecurity::start();
        if (!$this->authenticated()) {
            return $this->redirect('/login');
        }
        if (!SessionSecurity::verifyCsrfToken($request->body()['csrf_token'] ?? null)) {
            return new Response(400, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Your form session expired. Please try again.');
        }

        $body = $request->body();
        $address = [
            'full_name' => is_string($body['full_name'] ?? null) ? trim($body['full_name']) : '',
            'phone' => is_string($body['phone'] ?? null) ? trim($body['phone']) : '',
            'address_line_1' => is_string($body['address_line_1'] ?? null) ? trim($body['address_line_1']) : '',
            'address_line_2' => is_string($body['address_line_2'] ?? null) ? trim($body['address_line_2']) : '',
            'city' => is_string($body['city'] ?? null) ? trim($body['city']) : '',
            'postal_code' => is_string($body['postal_code'] ?? null) ? trim($body['postal_code']) : '',
            'country' => is_string($body['country'] ?? null) ? trim($body['country']) : '',
        ];
        if (self::validateAddress($address) !== []) {
            $_SESSION['address_error'] = implode(' ', self::validateAddress($address));
            return $this->redirect('/account/addresses');
        }

        $isDefault = ($body['is_default'] ?? '') === '1';
        try {
            (new UserRepository(Database::connect($this->databaseConfig)))
                ->addAddress((int) $_SESSION['user_id'], $address, $isDefault);
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }

        $_SESSION['address_notice'] = 'Address saved.';

        return $this->redirect('/account/addresses');
    }

    public function deleteAddress(array $params, Request $request): Response
    {
        SessionSecurity::start();
        if (!$this->authenticated()) {
            return $this->redirect('/login');
        }
        if (!SessionSecurity::verifyCsrfToken($request->body()['csrf_token'] ?? null)) {
            return new Response(400, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Your form session expired. Please try again.');
        }

        $addressId = filter_var($params['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($addressId === false) {
            return new Response(404, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Address not found.');
        }

        try {
            $deleted = (new UserRepository(Database::connect($this->databaseConfig)))
                ->deleteAddress((int) $_SESSION['user_id'], $addressId);
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }

        if (!$deleted) {
            return new Response(404, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Address not found.');
        }

        $_SESSION['address_notice'] = 'Address removed.';

        return $this->redirect('/account/addresses');
    }

    public static function validateRegistration(string $name, string $email, string $password): array
    {
        $errors = [];
        $nameLength = preg_match_all('/./us', $name);
        if ($name === '' || $nameLength === false || $nameLength > 120 || preg_match('/[\x00-\x1F\x7F]/', $name)) {
            $errors[] = 'Enter a name up to 120 characters.';
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 180) {
            $errors[] = 'Enter a valid email address.';
        }
        if (strlen($password) < 12 || strlen($password) > 72) {
            $errors[] = 'Use a password between 12 and 72 bytes.';
        }

        return $errors;
    }

    public static function validateAddress(array $address): array
    {
        $requiredFields = [
            'full_name' => ['Full name', 120],
            'phone' => ['Phone number', 30],
            'address_line_1' => ['Address', 255],
            'city' => ['City', 80],
            'country' => ['Country', 80],
        ];
        $errors = [];

        foreach ($requiredFields as $field => [$label, $maxLength]) {
            $value = $address[$field] ?? '';
            if (!is_string($value) || trim($value) === '' || strlen($value) > $maxLength || preg_match('/[\x00-\x1F\x7F]/', $value)) {
                $errors[] = 'Enter a valid ' . strtolower($label) . '.';
            }
        }

        foreach (['address_line_2' => 255, 'postal_code' => 20] as $field => $maxLength) {
            $value = $address[$field] ?? '';
            if (!is_string($value) || strlen($value) > $maxLength || preg_match('/[\x00-\x1F\x7F]/', $value)) {
                $errors[] = 'Check the optional address fields.';
                break;
            }
        }

        return $errors;
    }

    private function loginFailed(string $email): Response
    {
        $_SESSION['auth_error'] = 'Sign-in failed. Check your credentials or try again later.';
        $_SESSION['auth_email'] = $email;

        return $this->redirect('/login');
    }

    private function authenticated(): bool
    {
        return isset($_SESSION['user_id'], $_SESSION['user_role'])
            && $_SESSION['user_role'] === 'customer';
    }

    private function consumeFlash(string $key): string
    {
        $value = $_SESSION[$key] ?? '';
        unset($_SESSION[$key]);

        return is_string($value) ? $value : '';
    }

    private function render(string $template, array $data, int $status = 200): Response
    {
        $path = __DIR__ . '/../Views/' . $template;
        if (!is_file($path)) {
            throw new RuntimeException('Authentication template is missing.');
        }

        $data = array_merge([
            'title' => 'Customer account',
            'description' => 'Sign in or create a customer account at Helpyard.store.',
        ], $data);
        extract($data, EXTR_SKIP);
        ob_start();
        require $path;
        $html = ob_get_clean();
        if ($html === false) {
            throw new RuntimeException('Could not render authentication page.');
        }

        return new Response($status, ['Content-Type' => 'text/html; charset=UTF-8'], $html);
    }

    private function redirect(string $location): Response
    {
        return new Response(303, ['Location' => $location], '');
    }

    private function unavailable(PDOException | RuntimeException $exception): Response
    {
        error_log('Authentication request failed: ' . $exception->getMessage());

        return new Response(503, ['Content-Type' => 'text/html; charset=UTF-8'], 'Authentication is temporarily unavailable.');
    }
}
