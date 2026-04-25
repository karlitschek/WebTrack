<?php

declare(strict_types=1);

namespace OCA\WebTrack\Service;

use OCA\WebTrack\AppInfo\Application;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * YouTube Data API v3 adapter.
 *
 * Used for the "All of YouTube" search source type.  Each video result is
 * returned as a normalised entry array matching the shape FeedService produces,
 * so the existing scoring / dedup / notification pipeline works unchanged:
 *
 *   [
 *     'id'           => 'https://www.youtube.com/watch?v=VIDEO_ID',
 *     'title'        => 'Video title',
 *     'content'      => 'Video description',
 *     'pubDate'      => '2024-01-01T00:00:00Z',
 *     'channelTitle' => 'Channel name',
 *     'channelId'    => 'UCxxxxxx',
 *   ]
 */
class YouTubeService {

    private const SEARCH_URL = 'https://www.googleapis.com/youtube/v3/search';

    public function __construct(
        private IClientService  $clientService,
        private IConfig         $config,
        private LoggerInterface $logger,
    ) {
    }

    // -------------------------------------------------------------------------
    // API key helpers
    // -------------------------------------------------------------------------

    public function getApiKey(string $userId): string {
        return $this->config->getUserValue($userId, Application::APP_ID, 'youtube_api_key', '');
    }

    public function setApiKey(string $userId, string $apiKey): void {
        $this->config->setUserValue($userId, Application::APP_ID, 'youtube_api_key', trim($apiKey));
    }

    public function hasApiKey(string $userId): bool {
        return $this->getApiKey($userId) !== '';
    }

    // -------------------------------------------------------------------------
    // Search
    // -------------------------------------------------------------------------

    /**
     * Searches all of YouTube for videos matching $query (ordered by date).
     *
     * Returns entries in the same shape as FeedService::parseEntries() so they
     * can be fed directly into the existing processing pipeline.
     *
     * @return array<array{id:string,title:string,content:string,pubDate:string,channelTitle:string,channelId:string}>
     * @throws \RuntimeException on API error or missing key
     */
    public function search(string $query, string $apiKey, int $maxResults = 50): array {
        if ($apiKey === '') {
            throw new \RuntimeException('YouTube API key is not configured.');
        }

        $params = http_build_query([
            'key'        => $apiKey,
            'q'          => $query,
            'type'       => 'video',
            'part'       => 'snippet',
            'maxResults' => min($maxResults, 50),
            'order'      => 'date',
        ]);

        $url = self::SEARCH_URL . '?' . $params;
        $this->logger->debug('[webtrack/youtube] GET {url}', ['url' => preg_replace('/key=[^&]+/', 'key=***', $url)]);

        try {
            $response = $this->clientService->newClient()->get($url, [
                'timeout'         => 20,
                'connect_timeout' => 5,
                'headers'         => ['Accept' => 'application/json'],
            ]);
            $body = (string) $response->getBody();
        } catch (\Throwable $e) {
            throw new \RuntimeException('YouTube API request failed: ' . $e->getMessage(), 0, $e);
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            throw new \RuntimeException('YouTube API returned non-JSON response.');
        }
        if (isset($json['error'])) {
            $msg = $json['error']['message'] ?? 'Unknown error';
            throw new \RuntimeException('YouTube API error: ' . $msg);
        }

        $entries = [];
        foreach ($json['items'] ?? [] as $item) {
            $videoId      = $item['id']['videoId']               ?? null;
            $snippet      = $item['snippet']                     ?? [];
            $title        = $snippet['title']                    ?? '';
            $description  = $snippet['description']              ?? '';
            $channelTitle = $snippet['channelTitle']             ?? '';
            $channelId    = $snippet['channelId']                ?? '';
            $publishedAt  = $snippet['publishedAt']              ?? '';

            if ($videoId === null) continue;

            $entries[] = [
                'id'           => 'https://www.youtube.com/watch?v=' . $videoId,
                'title'        => html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'content'      => html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'pubDate'      => $publishedAt,
                'channelTitle' => html_entity_decode($channelTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'channelId'    => $channelId,
            ];
        }

        return $entries;
    }
}
