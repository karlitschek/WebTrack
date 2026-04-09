<?php

declare(strict_types=1);

namespace OCA\WebTrack\Controller;

use OCA\WebTrack\Db\HistoryLogMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class DashboardController extends Controller {

    public function __construct(
        string $appName,
        IRequest $request,
        private HistoryLogMapper $historyMapper,
        private ?string $userId,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function recentFinds(): JSONResponse {
        $rows = $this->historyMapper->findLatestFoundByUser($this->userId ?? '', 10);
        return new JSONResponse($rows);
    }
}
