<?php

declare(strict_types=1);

namespace OCA\WebTrack\Controller;

use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class TalkRoomController extends Controller {

    public function __construct(
        string $appName,
        IRequest $request,
        private IAppManager $appManager,
        private LoggerInterface $logger,
        private ?string $userId,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function index(): JSONResponse {
        if (!$this->appManager->isInstalled('spreed')) {
            return new JSONResponse([]);
        }

        try {
            /** @var \OCA\Talk\Manager $talkManager */
            $talkManager = \OCP\Server::get(\OCA\Talk\Manager::class);
            $rooms = $talkManager->getRoomsForUser($this->userId ?? '');
            $result = [];
            foreach ($rooms as $room) {
                $result[] = [
                    'token' => $room->getToken(),
                    'name'  => $room->getDisplayName($this->userId ?? ''),
                    'type'  => $room->getType(),
                ];
            }
            return new JSONResponse($result);
        } catch (\Throwable $e) {
            $this->logger->warning('[webtrack] Could not fetch Talk rooms: ' . $e->getMessage());
            return new JSONResponse([]);
        }
    }
}
