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
        private YouTubeService      $youtubeService,
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
        $this->applyGoogleNewsUrl($monitor);
        $this->applyYouTubeUrl($monitor, $data);
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
        $this->applyGoogleNewsUrl($monitor);
        $this->applyYouTubeUrl($monitor, $data);
        return $this->monitorMapper->update($monitor);
    }

    /**
     * For Google News monitors, auto-builds and stores the RSS feed URL from
     * the monitor's keyword, boostKeywords (positive terms), excludePatterns
     * (negative terms prefixed with -), and sourceLanguage.
     *
     * Example: keyword="Nextcloud", boost=["github"], exclude=["reddit","forum"]
     *   → https://news.google.com/rss/search?q=Nextcloud+github+-reddit+-forum&hl=en-US&gl=US&ceid=US:en
     */
    private function applyGoogleNewsUrl(Monitor $monitor): void {
        if ($monitor->getSourceType() !== 'google_news') {
            return;
        }

        $terms = [];

        // Main keyword — always first
        $kw = trim($monitor->getKeyword());
        if ($kw !== '') {
            $terms[] = $kw;
        }

        // Positive / boost keywords
        foreach ($monitor->getBoostKeywordsArray() as $k) {
            $k = trim($k);
            if ($k !== '') {
                $terms[] = $k;
            }
        }

        // Negative keywords (prefixed with -)
        foreach ($monitor->getExcludePatternsArray() as $k) {
            $k = trim($k);
            if ($k !== '') {
                $terms[] = '-' . $k;
            }
        }

        $q = implode('+', array_map('rawurlencode', $terms));

        // Parse language tag, e.g. "en-US" → hl=en-US, gl=US, ceid=US:en
        $lang   = $monitor->getSourceLanguage() ?: 'en-US';
        $parts  = explode('-', $lang, 2);
        $hl     = $lang;
        $gl     = strtoupper($parts[1] ?? $parts[0]);
        $ceid   = $gl . ':' . strtolower($parts[0]);

        $url = "https://news.google.com/rss/search?q={$q}&hl={$hl}&gl={$gl}&ceid={$ceid}";
        $monitor->setUrl($url);
        $monitor->setIsFeed(true);
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

    /**
     * For YouTube monitors, builds and stores the channel RSS feed URL from
     * the youtubeChannelId supplied in the request data.
     *
     * Example: channelId="UCxxxxxx"
     *   → https://www.youtube.com/feeds/videos.xml?channel_id=UCxxxxxx
     */
    private function applyYouTubeUrl(Monitor $monitor, array $data): void {
        if ($monitor->getSourceType() !== 'youtube') {
            return;
        }

        $channelId = trim($data['youtubeChannelId'] ?? '');
        if ($channelId === '') {
            return;
        }

        $url = 'https://www.youtube.com/feeds/videos.xml?channel_id=' . rawurlencode($channelId);
        $monitor->setUrl($url);
        $monitor->setIsFeed(true);
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
            $allowed = ['custom', 'google_news', 'youtube', 'youtube_search'];
            $type    = in_array($data['sourceType'], $allowed, true) ? $data['sourceType'] : 'custom';
            $monitor->setSourceType($type);
        }
        if (isset($data['sourceLanguage'])) {
            $monitor->setSourceLanguage(substr(trim($data['sourceLanguage']), 0, 10));
        }

        // Relevance scoring — auto-URL sources don't use a threshold (scoring is bypassed
        // at check time), so always store 0 for them to avoid confusion.
        $autoUrlTypes = ['google_news', 'youtube', 'youtube_search'];
        if (in_array($monitor->getSourceType(), $autoUrlTypes, true)) {
            $monitor->setScoreThreshold(0);
        } elseif (isset($data['scoreThreshold'])) {
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

    /**
     * Public entry point for the occ command: runs one check immediately,
     * ignoring the per-monitor interval gate.
     */
    public function runCheckForMonitor(Monitor $monitor): void {
        $this->executeCheck($monitor, ignoreInterval: true);
    }

    private function executeCheck(Monitor $monitor, bool $ignoreInterval = false): void {
        // Respect per-monitor interval (unless called from CLI with --force)
        if (!$ignoreInterval && $monitor->getLastCheckAt() !== null) {
            $lastCheck = new \DateTimeImmutable($monitor->getLastCheckAt());
            $due       = $lastCheck->modify('+' . $monitor->getCheckInterval() . ' minutes');
            if ($due > new \DateTimeImmutable()) {
                return; // Not yet due
            }
        }

        $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        $monitor->setLastCheckAt($now);

        try {
            if ($monitor->getSourceType() === 'youtube_search') {
                $apiKey  = $this->youtubeService->getApiKey($monitor->getUserId());
                $entries = $this->youtubeService->search($monitor->getKeyword(), $apiKey);
                $this->handleSuccess($monitor, null, $entries);
            } else {
                $content = $this->checkService->fetch($monitor->getUrl());
                $this->handleSuccess($monitor, $content);
            }
        } catch (\Throwable $e) {
            $this->handleCheckError($monitor, $e->getMessage());
        }

        $this->monitorMapper->update($monitor);
    }

    /**
     * @param array<array<string,string>>|null $preloadedEntries  Pre-fetched entries
     *        (YouTube search); when null, $content is parsed as feed or web page.
     */
    private function handleSuccess(Monitor $monitor, ?string $content, ?array $preloadedEntries = null): void {
        // Reset error streak on success
        if ($monitor->getConsecutiveErrors() > 0) {
            $monitor->setConsecutiveErrors(0);
        }
        // Clear error/failing status on successful fetch
        if (in_array($monitor->getStatus(), ['error', 'failing'], true)) {
            $monitor->setStatus('ok');
        }

        if ($preloadedEntries !== null) {
            // YouTube search — entries already fetched and normalised
            $new = $this->feedService->filterNewEntries($monitor->getId(), $preloadedEntries);
            $this->processNewEntries($monitor, $new);
        } elseif ($monitor->getIsFeed()) {
            $this->handleFeedContent($monitor, $content ?? '');
        } else {
            $this->handleWebContent($monitor, $content ?? '');
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
        $this->processNewEntries($monitor, $newEntries);
    }

    /**
     * Core entry-processing loop shared by RSS/Atom feeds and YouTube search.
     *
     * Each entry must have at minimum: id, title, content, pubDate.
     * YouTube search entries also carry channelTitle and channelId.
     *
     * @param array<array<string,string>> $newEntries
     */
    private function processNewEntries(Monitor $monitor, array $newEntries): void {
        // Fetch (or auto-create) the Tables column schema once per check cycle.
        $tableColumns = [];
        $headlineCol  = null;   // resolved once, reused for per-entry dedup
        if ($monitor->getTablesTableId() !== null) {
            try {
                $tableColumns = $this->tablesService->getColumnsForUser(
                    $monitor->getTablesTableId(),
                    $monitor->getUserId(),
                );
                // If the table exists but has no columns yet, bootstrap the PR
                // Coverage schema automatically so the first match writes a row.
                if ($tableColumns === []) {
                    $this->logger->info('[webtrack] Table {id} has no columns — creating PR Coverage schema', [
                        'id' => $monitor->getTablesTableId(),
                    ]);
                    $this->tablesService->ensurePrCoverageColumns(
                        $monitor->getTablesTableId(),
                        $monitor->getUserId(),
                    );
                    $tableColumns = $this->tablesService->getColumnsForUser(
                        $monitor->getTablesTableId(),
                        $monitor->getUserId(),
                    );
                }
            } catch (\Throwable $e) {
                $this->logger->warning('[webtrack] Could not load/init Tables columns for monitor {id}: {err}', [
                    'id'  => $monitor->getId(),
                    'err' => $e->getMessage(),
                ]);
            }

            // Locate the Headline column once — used for per-entry URL dedup.
            foreach ($tableColumns as $col) {
                if (strcasecmp($col['title'], 'headline') === 0) {
                    $headlineCol = $col;
                    break;
                }
            }
        }

        foreach ($newEntries as $entry) {
            $url          = $entry['id'];
            $title        = $entry['title'];
            $body         = $entry['content'];
            $pubDate      = $entry['pubDate']      ?? '';
            $channelTitle = $entry['channelTitle'] ?? '';   // YouTube search only

            // ── URL-level duplicate guard ──────────────────────────────────────
            // The feed's seenIds list caps at 500 entries, so an old article can
            // re-appear as "new" once the window slides.  Check the Tables row
            // directly (fast DB query, works in all contexts) to prevent both
            // duplicate table rows AND duplicate Talk notifications.
            if ($headlineCol !== null) {
                try {
                    if ($this->tablesService->rowExistsForUrl(
                        $monitor->getTablesTableId(),
                        $headlineCol['id'],
                        $url,
                    )) {
                        $this->logger->debug('[webtrack] entry skipped (already in table): {url}', ['url' => $url]);
                        continue;
                    }
                } catch (\Throwable $e) {
                    // If the check fails, proceed — better a duplicate than a miss.
                    $this->logger->debug('[webtrack] rowExistsForUrl failed, proceeding: {err}', ['err' => $e->getMessage()]);
                }
            }

            // $combinedForSnippet: title + body text — used for snippet extraction
            // and custom-source keyword matching.  Kept separate so that the
            // negative-keyword filter below (which appends the raw URL) does not
            // corrupt the snippet with e.g. long Google News base64 IDs.
            $combinedForSnippet = $title . ' ' . strip_tags($body);

            // Relevance filter:
            //   - custom: apply full boost/exclude scoring against configured threshold
            //   - google_news / youtube / youtube_search: the source already filters by
            //     topic (URL query / API call), so we only run negative-keyword exclusion
            //     as a safeguard and skip the score threshold entirely.
            $sourceType = $monitor->getSourceType();
            if ($sourceType === 'custom') {
                if ($monitor->getScoreThreshold() > 0 && !$this->scoringService->isRelevant($monitor, $url, $title, $body)) {
                    $this->logger->debug('[webtrack] entry skipped (score too low): {title}', [
                        'title' => mb_substr($title, 0, 80),
                    ]);
                    continue;
                }
            } else {
                // For feed/API sources: only apply negative-keyword (exclude) filtering.
                // Use a separate local variable so $combinedForSnippet stays clean.
                $filterText = mb_strtolower($title . ' ' . $url);
                $excluded   = false;
                foreach ($monitor->getExcludePatternsArray() as $pattern) {
                    if ($pattern !== '' && str_contains($filterText, mb_strtolower($pattern))) {
                        $this->logger->debug('[webtrack] entry skipped (negative keyword "{p}"): {title}', [
                            'p'     => $pattern,
                            'title' => mb_substr($title, 0, 80),
                        ]);
                        $excluded = true;
                        break;
                    }
                }
                if ($excluded) {
                    continue;
                }
            }

            $snippet = $this->snippetService->findSnippet($combinedForSnippet, $monitor->getKeyword(), $monitor->getUseRegex());
            if ($snippet !== null) {
                $monitor->setLastFoundAt((new \DateTimeImmutable())->format(\DateTimeInterface::ATOM));
                $monitor->setStatus('found');
                $this->notificationService->notifyFound($monitor, $snippet, $url);
                $this->logEvent($monitor, 'found', $snippet);

                if ($monitor->getTablesTableId() !== null && $tableColumns !== []) {
                    $this->insertTablesRow($monitor, $tableColumns, $url, $title, $pubDate, $body, $channelTitle);
                }
            }
        }
    }

    /**
     * Inserts a matched article as a new row in the monitor's configured table.
     *
     * The caller (processNewEntries) already performed the URL duplicate check
     * before calling this method, so no second check is needed here.
     *
     * @param array<array{id:int,title:string,type:string,selectionOptions?:array<array{id:int,label:string}>}> $columns
     */
    private function insertTablesRow(
        Monitor $monitor,
        array   $columns,
        string  $url,
        string  $title,
        string  $pubDate,
        string  $body         = '',
        string  $channelTitle = '',
    ): void {
        try {
            $data = $this->tablesRowBuilder->build(
                columns:      $columns,
                monitor:      $monitor,
                entryUrl:     $url,
                title:        $title,
                pubDate:      $pubDate,
                body:         $body,
                campaignId:   $monitor->getTablesCampaignId(),
                channelTitle: $channelTitle,
            );
            $this->tablesService->insertRowForUser($monitor->getTablesTableId(), $data, $monitor->getUserId());
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
