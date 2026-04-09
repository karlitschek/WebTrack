<?php

declare(strict_types=1);

namespace OCA\WebTrack\Service;

use OCA\WebTrack\Db\HistoryLog;
use OCA\WebTrack\Db\HistoryLogMapper;
use OCA\WebTrack\Db\Monitor;
use OCA\WebTrack\Db\MonitorMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

class MonitorService {

    private const MAX_MONITORS_PER_USER = 100;

    public function __construct(
        private MonitorMapper       $monitorMapper,
        private HistoryLogMapper    $historyMapper,
        private CheckService        $checkService,
        private FeedService         $feedService,
        private SnippetService      $snippetService,
        private NotificationService $notificationService,
        private IL10N               $l,
        private LoggerInterface     $logger,
    ) {
    }

    // -------------------------------------------------------------------------
    // CRUD helpers used by controllers
    // -------------------------------------------------------------------------

    /** @return Monitor[] */
    public function listForUser(string $userId): array {
        return $this->monitorMapper->findAllByUser($userId);
    }

    /** @throws DoesNotExistException */
    public function getForUser(int $id, string $userId): Monitor {
        return $this->monitorMapper->findByIdAndUser($id, $userId);
    }

    public function create(string $userId, array $data): Monitor {
        $existing = $this->monitorMapper->findAllByUser($userId);
        if (count($existing) >= self::MAX_MONITORS_PER_USER) {
            throw new \OverflowException($this->l->t('Maximum number of monitors (%s) reached', [self::MAX_MONITORS_PER_USER]));
        }

        $monitor = new Monitor();
        $monitor->setUserId($userId);
        $this->applyData($monitor, $data);
        $monitor->setIsActive(true);
        $monitor->setIsFeed($monitor->getIsFeed());
        $monitor->setStatus('ok');
        $monitor->setConsecutiveErrors(0);
        $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        $monitor->setCreatedAt($now);
        return $this->monitorMapper->insert($monitor);
    }

    /** @throws DoesNotExistException */
    public function update(int $id, string $userId, array $data): Monitor {
        $monitor = $this->monitorMapper->findByIdAndUser($id, $userId);
        $this->applyData($monitor, $data);
        return $this->monitorMapper->update($monitor);
    }

    /** @throws DoesNotExistException */
    public function delete(int $id, string $userId): void {
        $monitor = $this->monitorMapper->findByIdAndUser($id, $userId);
        $this->historyMapper->deleteByMonitor($monitor->getId());
        $this->feedService->deleteState($monitor->getId());
        $this->monitorMapper->delete($monitor);
    }

    /** @throws DoesNotExistException */
    public function setPaused(int $id, string $userId, bool $paused): Monitor {
        $monitor = $this->monitorMapper->findByIdAndUser($id, $userId);
        $monitor->setIsActive(!$paused);
        $monitor->setStatus($paused ? 'paused' : 'ok');
        $monitor = $this->monitorMapper->update($monitor);

        $this->logEvent($monitor, $paused ? 'paused' : 'resumed');
        return $monitor;
    }

    private function applyData(Monitor $monitor, array $data): void {
        if (isset($data['name']))          { $monitor->setName(trim($data['name'])); }
        if (isset($data['url']))           { $monitor->setUrl(trim($data['url'])); }
        if (isset($data['keyword']))       { $monitor->setKeyword(trim($data['keyword'])); }
        if (isset($data['checkInterval'])) { $monitor->setCheckInterval(max(5, (int) $data['checkInterval'])); }
        if (isset($data['isFeed']))        { $monitor->setIsFeed((bool) $data['isFeed']); }
        if (isset($data['useRegex']))      { $monitor->setUseRegex((bool) $data['useRegex']); }
        if (array_key_exists('talkRoomToken', $data)) {
            $token = $data['talkRoomToken'];
            $monitor->setTalkRoomToken(($token !== '' && $token !== null) ? (string) $token : null);
        }
    }

    // -------------------------------------------------------------------------
    // Background check runner
    // -------------------------------------------------------------------------

    public function runAllChecks(): void {
        $monitors = $this->monitorMapper->findAllActive();
        foreach ($monitors as $monitor) {
            try {
                $this->executeCheck($monitor);
            } catch (\Throwable $e) {
                $this->logger->error('[webtrack] Unexpected error checking monitor {id}: {err}', [
                    'id'  => $monitor->getId(),
                    'err' => $e->getMessage(),
                ]);
            }
        }
    }

    private function executeCheck(Monitor $monitor): void {
        // Respect per-monitor interval
        if ($monitor->getLastCheckAt() !== null) {
            $lastCheck = new \DateTimeImmutable($monitor->getLastCheckAt());
            $due       = $lastCheck->modify('+' . $monitor->getCheckInterval() . ' minutes');
            if ($due > new \DateTimeImmutable()) {
                return; // Not yet due
            }
        }

        $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        $monitor->setLastCheckAt($now);

        try {
            $content = $this->checkService->fetch($monitor->getUrl());
            $this->handleSuccess($monitor, $content);
        } catch (\Throwable $e) {
            $this->handleCheckError($monitor, $e->getMessage());
        }

        $this->monitorMapper->update($monitor);
    }

    private function handleSuccess(Monitor $monitor, string $content): void {
        // Reset error streak on success
        if ($monitor->getConsecutiveErrors() > 0) {
            $monitor->setConsecutiveErrors(0);
        }
        // Clear error/failing status on successful fetch
        if (in_array($monitor->getStatus(), ['error', 'failing'], true)) {
            $monitor->setStatus('ok');
        }

        if ($monitor->getIsFeed()) {
            $this->handleFeedContent($monitor, $content);
        } else {
            $this->handleWebContent($monitor, $content);
        }
    }

    private function handleWebContent(Monitor $monitor, string $rawContent): void {
        $text    = $this->checkService->htmlToText($rawContent);
        $snippet = $this->snippetService->findSnippet($text, $monitor->getKeyword(), $monitor->getUseRegex());

        if ($snippet !== null) {
            $hash = $this->snippetService->findContextHash($text, $monitor->getKeyword(), $monitor->getUseRegex());

            if ($hash !== $monitor->getLastFoundHash()) {
                $monitor->setLastFoundHash($hash);
                $monitor->setLastFoundAt((new \DateTimeImmutable())->format(\DateTimeInterface::ATOM));
                $monitor->setStatus('found');
                $this->notificationService->notifyFound($monitor, $snippet);
                $this->logEvent($monitor, 'found', $snippet);
            }
        } else {
            if ($monitor->getStatus() === 'found') {
                $monitor->setStatus('ok');
                $monitor->setLastFoundAt(null);
                $monitor->setLastFoundHash(null);
            }
        }
    }

    private function handleFeedContent(Monitor $monitor, string $content): void {
        $entries = $this->feedService->parseEntries($content);
        $newEntries = $this->feedService->filterNewEntries($monitor->getId(), $entries);

        foreach ($newEntries as $entry) {
            $combined = $entry['title'] . ' ' . strip_tags($entry['content']);
            $snippet  = $this->snippetService->findSnippet($combined, $monitor->getKeyword(), $monitor->getUseRegex());
            if ($snippet !== null) {
                $monitor->setLastFoundAt((new \DateTimeImmutable())->format(\DateTimeInterface::ATOM));
                $monitor->setStatus('found');
                $this->notificationService->notifyFound($monitor, $snippet);
                $this->logEvent($monitor, 'found', $snippet);
            }
        }
    }

    private function handleCheckError(Monitor $monitor, string $errorMsg): void {
        $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        $monitor->setLastErrorAt($now);
        $monitor->setLastErrorMsg(mb_substr($errorMsg, 0, 2048));
        $monitor->setConsecutiveErrors($monitor->getConsecutiveErrors() + 1);
        $monitor->setStatus($monitor->getConsecutiveErrors() >= 5 ? 'failing' : 'error');
        $this->notificationService->notifyErrorIfNeeded($monitor, $errorMsg);
        $this->logEvent($monitor, 'error', null, $errorMsg);
    }

    private function logEvent(Monitor $monitor, string $event, ?string $snippet = null, ?string $errorMsg = null): void {
        $log = new HistoryLog();
        $log->setMonitorId($monitor->getId());
        $log->setUserId($monitor->getUserId());
        $log->setEvent($event);
        $log->setSnippet($snippet);
        $log->setErrorMsg($errorMsg !== null ? mb_substr($errorMsg, 0, 2048) : null);
        $log->setCreatedAt((new \DateTimeImmutable())->format(\DateTimeInterface::ATOM));
        $this->historyMapper->insert($log);
    }
}
