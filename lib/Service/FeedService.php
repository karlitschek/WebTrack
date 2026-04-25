<?php

declare(strict_types=1);

namespace OCA\WebTrack\Service;

use OCA\WebTrack\Db\FeedState;
use OCA\WebTrack\Db\FeedStateMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class FeedService {
    private const MAX_SEEN = 500;

    public function __construct(
        private FeedStateMapper $feedStateMapper,
    ) {
    }

    /**
     * Detects whether content looks like an RSS/Atom feed.
     */
    public function isFeed(string $content): bool {
        $trimmed = ltrim($content);
        if (!str_starts_with($trimmed, '<')) {
            return false;
        }
        return (bool) preg_match('/<(rss|feed|rdf:RDF)\b/i', $trimmed);
    }

    /**
     * Parses an RSS or Atom feed and returns an array of entries.
     * Each entry: ['id' => string, 'title' => string, 'content' => string]
     *
     * @return array<array{id:string,title:string,content:string}>
     */
    public function parseEntries(string $xml): array {
        libxml_use_internal_errors(true);
        $previous = libxml_disable_entity_loader(true);
        $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
        libxml_disable_entity_loader($previous);
        if ($doc === false) {
            return [];
        }

        $entries = [];
        $ns = $doc->getNamespaces(true);

        // Atom feed
        if (str_contains((string) $doc->getName(), 'feed')) {
            $atomNs = $ns[''] ?? null;
            $children = $atomNs ? $doc->children($atomNs) : $doc;
            foreach ($children->entry as $entry) {
                $id      = (string) ($entry->id ?? '');
                $title   = (string) ($entry->title ?? '');
                $content = (string) ($entry->content ?? $entry->summary ?? '');
                $pubDate = (string) ($entry->published ?? $entry->updated ?? '');
                $entries[] = ['id' => $id ?: $title, 'title' => $title, 'content' => $content, 'pubDate' => $pubDate];
            }
            return $entries;
        }

        // RSS feed
        $items = $doc->channel->item ?? $doc->item ?? [];
        foreach ($items as $item) {
            $id      = (string) ($item->guid ?? $item->link ?? '');
            $title   = (string) ($item->title ?? '');
            $content = (string) ($item->description ?? '');
            $pubDate = (string) ($item->pubDate ?? $item->pubdate ?? '');
            // Try content:encoded
            foreach ($ns as $prefix => $uri) {
                if (str_contains($uri, 'content')) {
                    $ext = $item->children($uri);
                    if (isset($ext->encoded)) {
                        $content = (string) $ext->encoded;
                    }
                }
            }
            $entries[] = ['id' => $id ?: $title, 'title' => $title, 'content' => $content, 'pubDate' => $pubDate];
        }

        return $entries;
    }

    /**
     * Returns only entries not yet seen. On first call (no state) returns empty
     * array and saves all current IDs as "seen" to avoid spamming old content.
     *
     * @param array<array{id:string,title:string,content:string}> $entries
     * @return array<array{id:string,title:string,content:string}>
     */
    public function filterNewEntries(int $monitorId, array $entries): array {
        try {
            $state   = $this->feedStateMapper->findByMonitor($monitorId);
            $seenIds = json_decode($state->getSeenIds(), true) ?? [];
            $isFirst = false;
        } catch (DoesNotExistException) {
            $state   = null;
            $seenIds = [];
            $isFirst = true;
        }

        $newEntries = [];
        $allIds     = $seenIds;

        foreach ($entries as $entry) {
            $entryId = $entry['id'];
            if (!$isFirst && !in_array($entryId, $seenIds, true)) {
                $newEntries[] = $entry;
            }
            if (!in_array($entryId, $allIds, true)) {
                $allIds[] = $entryId;
            }
        }

        // Trim to MAX_SEEN keeping newest
        if (count($allIds) > self::MAX_SEEN) {
            $allIds = array_slice($allIds, -self::MAX_SEEN);
        }

        $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);

        if ($state === null) {
            $state = new FeedState();
            $state->setMonitorId($monitorId);
            $state->setSeenIds(json_encode($allIds));
            $state->setUpdatedAt($now);
            $this->feedStateMapper->insert($state);
        } else {
            $state->setSeenIds(json_encode($allIds));
            $state->setUpdatedAt($now);
            $this->feedStateMapper->update($state);
        }

        return $newEntries;
    }

    public function deleteState(int $monitorId): void {
        $this->feedStateMapper->deleteByMonitor($monitorId);
    }
}
