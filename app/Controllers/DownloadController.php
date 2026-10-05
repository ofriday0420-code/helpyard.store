<?php

namespace Helpyard\App\Controllers;

use Helpyard\App\Core\Database;
use Helpyard\App\Core\Request;
use Helpyard\App\Core\Response;
use Helpyard\App\Core\SessionSecurity;
use Helpyard\App\Repositories\DownloadRepository;
use Helpyard\App\Services\DigitalDeliveryService;
use PDOException;
use RuntimeException;

class DownloadController
{
    public function __construct(private array $databaseConfig, private string $privateStoragePath)
    {
    }

    public function index(array $params = [], ?Request $request = null): Response
    {
        SessionSecurity::start();
        if (!$this->authenticated()) {
            return $this->redirect('/login');
        }

        try {
            $downloads = (new DownloadRepository(Database::connect($this->databaseConfig)))
                ->forCustomer((int) $_SESSION['user_id']);
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }

        $title = 'My downloads';
        $description = 'Access private files included with your paid orders.';
        ob_start();
        require __DIR__ . '/../Views/auth/downloads.php';
        $html = ob_get_clean();
        if ($html === false) {
            throw new RuntimeException('Could not render the customer downloads page.');
        }

        return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $html);
    }

    public function download(array $params, ?Request $request = null): Response
    {
        SessionSecurity::start();
        if (!$this->authenticated()) {
            return $this->redirect('/login');
        }

        $entitlementId = CartController::positiveInteger($params['id'] ?? null, PHP_INT_MAX);
        if ($entitlementId === null) {
            return $this->notFound();
        }

        try {
            $download = (new DownloadRepository(Database::connect($this->databaseConfig)))
                ->findForCustomer($entitlementId, (int) $_SESSION['user_id']);
            if ($download === null) {
                return $this->notFound();
            }

            $privateFile = DigitalDeliveryService::resolvePrivateFile(
                $this->privateStoragePath,
                $download['storage_key'],
                dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public'
            );
            $downloadName = DigitalDeliveryService::safeDownloadName($download['download_name']);
            $asciiName = preg_replace('/[^A-Za-z0-9._-]/', '_', $downloadName);
            if (!is_string($asciiName) || $asciiName === '') {
                $asciiName = 'download';
            }
        } catch (PDOException $exception) {
            return $this->unavailable($exception);
        } catch (RuntimeException $exception) {
            error_log('Private download could not be served: ' . $exception->getMessage());
            return $this->notFound();
        }

        $encodedName = rawurlencode($downloadName);
        return new Response(200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . $encodedName,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], static function () use ($privateFile): void {
            $handle = fopen($privateFile, 'rb');
            if ($handle === false) {
                throw new RuntimeException('Could not open the authorized private download.');
            }
            try {
                if (fpassthru($handle) === false) {
                    throw new RuntimeException('Could not stream the authorized private download.');
                }
            } finally {
                fclose($handle);
            }
        });
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
        return new Response(404, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Download not found.');
    }

    private function unavailable(PDOException | RuntimeException $exception): Response
    {
        error_log('Customer downloads request failed: ' . $exception->getMessage());

        return new Response(503, ['Content-Type' => 'text/html; charset=UTF-8'], 'Downloads are temporarily unavailable.');
    }
}
