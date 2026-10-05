<?php

namespace Helpyard\App\Controllers;

use Helpyard\App\Core\Database;
use Helpyard\App\Core\AdminFileException;
use Helpyard\App\Core\Request;
use Helpyard\App\Core\Response;
use Helpyard\App\Core\SessionSecurity;
use Helpyard\App\Repositories\AdminFileRepository;
use Helpyard\App\Services\PrivateFileUploadService;
use PDOException;
use RuntimeException;

class AdminFileController
{
    public function __construct(private array $databaseConfig, private string $privateStoragePath)
    {
    }

    public function index(array $params = [], ?Request $request = null): Response
    {
        SessionSecurity::start();
        if (!$this->isAdmin()) {
            return $this->forbiddenOrLogin();
        }

        try {
            $products = (new AdminFileRepository(Database::connect($this->databaseConfig)))->productsWithFiles();
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }

        $title = 'Private product files';
        $description = 'Manage protected downloads for digital products, courses, books, and websites.';
        $csrfToken = SessionSecurity::csrfToken();
        $notice = $this->consumeFlash('admin_files_notice');
        $error = $this->consumeFlash('admin_files_error');
        ob_start();
        require __DIR__ . '/../Views/admin/files.php';
        $html = ob_get_clean();
        if ($html === false) {
            throw new RuntimeException('Could not render the private file manager.');
        }

        return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $html);
    }

    public function upload(array $params, Request $request): Response
    {
        SessionSecurity::start();
        if (!$this->isAdmin()) {
            return $this->forbiddenOrLogin();
        }
        if (!SessionSecurity::verifyCsrfToken($request->body()['csrf_token'] ?? null)) {
            return new Response(400, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Your form session expired. Please try again.');
        }

        $productId = CartController::positiveInteger($request->body()['product_id'] ?? null, PHP_INT_MAX);
        if ($productId === null) {
            $_SESSION['admin_files_error'] = 'Choose a product for this private file.';
            return $this->redirect('/admin/files');
        }
        $upload = $request->files()['asset'] ?? null;
        if (!is_array($upload)) {
            $_SESSION['admin_files_error'] = 'Choose a file to upload.';
            return $this->redirect('/admin/files');
        }

        $storedFile = null;
        try {
            $storedFile = PrivateFileUploadService::storeUploaded(
                $upload,
                $productId,
                $this->privateStoragePath,
                dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public'
            );
        } catch (RuntimeException $exception) {
            $_SESSION['admin_files_error'] = $exception->getMessage();
            return $this->redirect('/admin/files');
        }

        try {
            (new AdminFileRepository(Database::connect($this->databaseConfig)))->addFile(
                $productId,
                (int) $_SESSION['user_id'],
                $storedFile['storage_key'],
                $storedFile['download_name'],
                $storedFile['file_size'],
                $storedFile['mime_type']
            );
        } catch (AdminFileException $exception) {
            $this->removeUnregisteredFile($storedFile);
            $_SESSION['admin_files_error'] = $exception->getMessage();
            return $this->redirect('/admin/files');
        } catch (PDOException | RuntimeException $exception) {
            $this->removeUnregisteredFile($storedFile);
            return $this->unavailable($exception);
        }

        $_SESSION['admin_files_notice'] = 'Private file uploaded. Existing paid orders have been granted access.';

        return $this->redirect('/admin/files');
    }

    public function revoke(array $params, Request $request): Response
    {
        SessionSecurity::start();
        if (!$this->isAdmin()) {
            return $this->forbiddenOrLogin();
        }
        if (!SessionSecurity::verifyCsrfToken($request->body()['csrf_token'] ?? null)) {
            return new Response(400, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Your form session expired. Please try again.');
        }

        $fileId = CartController::positiveInteger($params['id'] ?? null, PHP_INT_MAX);
        if ($fileId === null) {
            $_SESSION['admin_files_error'] = 'Choose a valid file to revoke.';
            return $this->redirect('/admin/files');
        }

        try {
            $revoked = (new AdminFileRepository(Database::connect($this->databaseConfig)))
                ->revokeFile($fileId, (int) $_SESSION['user_id']);
        } catch (AdminFileException $exception) {
            $_SESSION['admin_files_error'] = $exception->getMessage();
            return $this->redirect('/admin/files');
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }
        if (!$revoked) {
            $_SESSION['admin_files_error'] = 'That file is already inactive or does not exist.';
            return $this->redirect('/admin/files');
        }

        $_SESSION['admin_files_notice'] = 'File access revoked for all customers.';

        return $this->redirect('/admin/files');
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

    private function removeUnregisteredFile(?array $file): void
    {
        if ($file !== null && is_file($file['absolute_path']) && !unlink($file['absolute_path'])) {
            error_log('Could not remove an unregistered private upload: ' . $file['storage_key']);
        }
    }

    private function redirect(string $location): Response
    {
        return new Response(303, ['Location' => $location], '');
    }

    private function unavailable(PDOException | RuntimeException $exception): Response
    {
        error_log('Private file administration failed: ' . $exception->getMessage());

        return new Response(503, ['Content-Type' => 'text/html; charset=UTF-8'], 'Private file administration is temporarily unavailable.');
    }
}
