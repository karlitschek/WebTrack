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
        private ScoringService      $scoringService,
        private TablesService       $tablesService,
        private TablesRowBuilder    $tablesRowBuilder,
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

        // Source configuration
        if (isset($data['sourceType'])) {
            $allowed = ['custom', 'google_news', 'youtube'];
            $type    = in_array($data['sourceType'], $allowed, true) ? $data['sourceType'] : 'custom';
            $monitor->setSourceType($type);
        }
        if (isset($data['sourceLanguage'])) {
            $monitor->setSourceLanguage(substr(trim($data['sourceLanguage']), 0, 10));
        }

        // Relevance scoring
        if (isset($data['scoreThreshold'])) {
            $monitor->setScoreThreshold(max(0, (int) $data['scoreThreshold']));
        }
        if (isset($data['boostKeywords'])) {
            $decoded = is_array($data['boostKeywords']) ? $data['boostKeywords'] : (json_decode($data['boostKeywords'], true) ?? []);
            $monitor->setBoostKeywords(json_encode(array_values(array_filter($decoded, 'is_string'))) ?: '[]');
        }
        if (isset($data['excludePatterns'])) {
            $decoded = is_array($data['excludePatterns']) ? $data['excludePatterns'] : (json_decode($data['excludePatterns'], true) ?? []);
            $monitor->setExcludePatterns(json_encode(array_values(array_filter($decoded, 'is_string'))) ?: '[]');
        }

        // Nextcloud Tables integration
        if (array_key_exists('tablesTableId', $data)) {
            $id = $data['tablesTableId'];
            $monitor->setTablesTableId(($id !== null && $id !== '') ? (int) $id : null);
        }
        if (array_key_exists('tablesCampaignId', $data)) {
            $id = $data['tablesCampaignId'];
            $monitor->setTablesCampaignId(($id !== null && $id !== '') ? (int) $id : null);
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
        $entries    = $this->feedService->parseEntries($content);
        $newEntries = $this->feedService->filterNewEntries($monitor->getId(), $entries);

        // Fetch the Tables column schema once (if configured) to avoid N requests.
        $tableColumns = [];
        if ($monitor->getTablesTableId() !== null) {
            try {
                $tableColumns = $this->tablesService->getColumns($monitor->getTablesTableId());
            } catch (\Throwable $e) {
                $this->logger->warning('[webtrack] Could not load Tables columns for monitor {id}: {err}', [
                    'id'  => $monitor->getId(),
                    'err' => $e->getMessage(),
                ]);
            }
        }

        foreach ($newEntries as $entry) {
            $url     = $entry['id'];   // feed entry ID is typically the article URL
            $title   = $entry['title'];
            $body    = $entry['content'];
            $pubDate = $entry['pubDate'] ?? '';
            $combined = $title . ' ' . strip_tags($body);

            // Relevance filter: only applied for non-custom source types or when
            // the monitor has a non-zero threshold / non-empty boost/exclude lists.
            if ($monitor->getSourceType() !== 'custom' || $monitor->getScoreThreshold() > 0) {
                if (!$this->scoringService->isRelevant($monitor, $url, $title, $body)) {
                    $this->logger->debug('[webtrack] feed entry skipped (score too low): {title}', [
                        'title' => mb_substr($title, 0, 80),
                    ]);
                    continue;
                }
            }

            $snippet = $this->snippetService->findSnippet($combined, $monitor->getKeyword(), $monitor->getUseRegex());
            if ($snippet !== null) {
                $monitor->setLastFoundAt((new \DateTimeImmutable())->format(\DateTimeInterface::ATOM));
                $monitor->setStatus('found');
                $this->notificationService->notifyFound($monitor, $snippet);
                $this->logEvent($monitor, 'found', $snippet);

                // Write to Nextcloud Tables when configured.
                if ($monitor->getTablesTableId() !== null && $tableColumns !== []) {
                    $this->insertTablesRow($monitor, $tableColumns, $url, $title, $pubDate);
                }
            }
        }
    }

    /**
     * Inserts a matched article as a new row in the monitor's configured table,
     * unless a row with the same article URL already exists (duplicate check).
     *
     * @param array<array{id:int,title:string,type:string,selectionOptions?:array<array{id:int,label:string}>}> $columns
     */
    private function insertTablesRow(
        Monitor $monitor,
        array   $columns,
        string  $url,
        string  $title,
        string  $pubDate,
    ): void {
        try {
            // Locate the Headline column for duplicate detection.
            $headlineCol = null;
            foreach ($columns as $col) {
                if (strcasecmp($col['title'], 'headline') === 0) {
                    $headlineCol = $col;
                    break;
                }
            }

            // Duplicate check: skip if the URL is already in the table.
            if ($headlineCol !== null) {
                $isDuplicate = $this->tablesService->rowExistsForUrl(
                    $monitor->getTablesTableId(),
                    $headlineCol['id'],
                    $url,
                );
                if ($isDuplicate) {
                    $this->logger->debug('[webtrack] Tables row skipped (duplicate URL) for monitor {id}: {url}', [
                        'id'  => $monitor->getId(),
                        'url' => $url,
                    ]);
                    return;
                }
            }

            $data = $this->tablesRowBuilder->build(
                columns:    $columns,
                monitor:    $monitor,
                entryUrl:   $url,
                title:      $title,
                pubDate:    $pubDate,
                campaignId: $monitor->getTablesCampaignId(),
            );
            $this->tablesService->insertRow($monitor->getTablesTableId(), $data);
            $this->logger->info('[webtrack] Tables row inserted for monitor {id}: {title}', [
                'id'    => $monitor->getId(),
                'title' => mb_substr($title, 0, 80),
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('[webtrack] Tables insert failed for monitor {id}: {err}', [
                'id'  => $monitor->getId(),
                'err' => $e->getMessage(),
            ]);
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
