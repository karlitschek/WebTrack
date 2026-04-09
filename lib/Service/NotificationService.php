<?php

declare(strict_types=1);

namespace OCA\WebTrack\Service;

use OCA\WebTrack\AppInfo\Application;
use OCA\WebTrack\Db\Monitor;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;

class NotificationService {

    public function __construct(
        private INotificationManager $notificationManager,
        private IAppManager $appManager,
        private IL10N $l,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Send a "keyword found" notification and optionally post to Talk.
     */
    public function notifyFound(Monitor $monitor, string $snippet): void {
        $this->sendNotification(
            $monitor->getUserId(),
            'keyword_found',
            [
                'monitorId'   => $monitor->getId(),
                'monitorName' => $monitor->getName(),
                'keyword'     => $monitor->getKeyword(),
                'snippet'     => $snippet,
            ]
        );

        if ($monitor->getTalkRoomToken()) {
            $message = $this->l->t('🔔 WebTrack: keyword "%1$s" found on %2$s — %3$s', [
                $monitor->getKeyword(),
                $monitor->getName(),
                $snippet,
            ]);
            $this->postToTalkRoom($monitor->getTalkRoomToken(), $message, $monitor->getUserId());
        }
    }

    /**
     * Send an error notification only on the 3rd and 5th consecutive error.
     */
    public function notifyErrorIfNeeded(Monitor $monitor, string $errorMsg): void {
        $count = $monitor->getConsecutiveErrors();
        if ($count !== 3 && $count !== 5) {
            return;
        }
        $this->sendNotification(
            $monitor->getUserId(),
            'check_error',
            [
                'monitorId'   => $monitor->getId(),
                'monitorName' => $monitor->getName(),
                'errorMsg'    => mb_substr($errorMsg, 0, 200),
                'errorCount'  => $count,
            ]
        );
    }

    private function sendNotification(string $userId, string $subject, array $params): void {
        $notification = $this->notificationManager->createNotification();
        $notification->setApp(Application::APP_ID)
            ->setUser($userId)
            ->setSubject($subject, $params)
            ->setObject('monitor', (string) ($params['monitorId'] ?? '0'))
            ->setDateTime(new \DateTime());
        $this->notificationManager->notify($notification);
    }

    /**
     * Post a message to a Talk room using Talk's internal API.
     */
    private function postToTalkRoom(string $roomToken, string $message, string $userId): void {
        if (!$this->appManager->isInstalled('spreed')) {
            $this->logger->debug('[webtrack] Talk (spreed) is not installed, skipping Talk notification');
            return;
        }

        try {
            $talkManager = \OCP\Server::get(\OCA\Talk\Manager::class);
            $chatManager = \OCP\Server::get(\OCA\Talk\Chat\ChatManager::class);

            $room = $talkManager->getRoomByToken($roomToken);

            $creationDateTime = new \DateTime();
            $chatManager->sendMessage(
                $room,
                null,            // participant
                'users',         // actorType
                $userId,         // actorId
                $message,
                $creationDateTime,
                null,            // replyTo
                '',              // referenceId
                false,           // silent
            );
        } catch (\Throwable $e) {
            $this->logger->warning('[webtrack] Failed to post to Talk room {token}: {err}', [
                'token' => $roomToken,
                'err'   => $e->getMessage(),
            ]);
        }
    }
}
