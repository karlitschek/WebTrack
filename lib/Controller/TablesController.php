<?php

declare(strict_types=1);

namespace OCA\WebTrack\Controller;

use OCA\WebTrack\Service\TablesService;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Proxies Nextcloud Tables metadata to the WebTrack frontend.
 *
 * Endpoints:
 *   GET /apps/webtrack/api/v1/tables          – list accessible tables
 *   GET /apps/webtrack/api/v1/tables/{id}     – list columns for a table
 */
class TablesController extends Controller {

    public function __construct(
        string $appName,
        IRequest $request,
        private IAppManager     $appManager,
        private TablesService   $tablesService,
        private IUserSession    $userSession,
        private LoggerInterface $logger,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Returns all Tables the current user can access, or an empty array when
     * the Tables app is not installed.
     */
    #[NoAdminRequired]
    public function index(): JSONResponse {
        if (!$this->appManager->isInstalled('tables')) {
            return new JSONResponse([]);
        }

        $userId = $this->userSession->getUser()?->getUID() ?? '';
        try {
            return new JSONResponse($this->tablesService->listTablesForUser($userId));
        } catch (\Throwable $e) {
            $this->logger->warning('[webtrack] Could not list Tables: ' . $e->getMessage());
            return new JSONResponse([]);
        }
    }

    /**
     * Returns the columns for the given table.  Used by MonitorForm to build
     * a human-readable preview of what will be written.
     *
     * @param int $id Table ID
     */
    #[NoAdminRequired]
    public function columns(int $id): JSONResponse {
        if (!$this->appManager->isInstalled('tables')) {
            return new JSONResponse([]);
        }

        $userId = $this->userSession->getUser()?->getUID() ?? '';
        try {
            return new JSONResponse($this->tablesService->getColumnsForUser($id, $userId));
        } catch (\Throwable $e) {
            $this->logger->warning('[webtrack] Could not get columns for table ' . $id . ': ' . $e->getMessage());
            return new JSONResponse([]);
        }
    }
}
