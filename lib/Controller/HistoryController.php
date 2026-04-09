<?php

declare(strict_types=1);

namespace OCA\WebTrack\Controller;

use OCA\WebTrack\Db\HistoryLogMapper;
use OCA\WebTrack\Service\MonitorService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;

class HistoryController extends Controller {

    public function __construct(
        string $appName,
        IRequest $request,
        private MonitorService $monitorService,
        private HistoryLogMapper $historyMapper,
        private IL10N $l,
        private ?string $userId,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function index(int $monitorId): JSONResponse {
        try {
            $this->monitorService->getForUser($monitorId, $this->userId ?? '');
        } catch (DoesNotExistException) {
            return new JSONResponse(['error' => $this->l->t('Not found')], Http::STATUS_NOT_FOUND);
        }

        $page  = max(0, (int) $this->request->getParam('page', 0));
        $limit = 50;
        $logs  = $this->historyMapper->findByMonitor($monitorId, $limit, $page * $limit);
        return new JSONResponse(array_map(fn($l) => $l->jsonSerialize(), $logs));
    }
}
