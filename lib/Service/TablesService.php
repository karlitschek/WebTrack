<?php

declare(strict_types=1);

namespace OCA\WebTrack\Service;

use OCP\Http\Client\IClientService;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

/**
 * Thin wrapper around the Nextcloud Tables REST API v1.
 *
 * Talks to the *same* Nextcloud instance via its internal base URL so we
 * never need to know the public hostname at runtime.  Authentication uses the
 * currently-logged-in user's session (cookie forwarded automatically by
 * IClientService) when called from a controller, or an OCS-share token when
 * called from a background job.
 *
 * All public methods throw \RuntimeException on network/HTTP failure and
 * return decoded PHP arrays on success.
 */
class TablesService {
    /** @var string Cached Tables API base URL */
    private string $apiBase = '';

    public function __construct(
        private IClientService  $clientService,
        private IURLGenerator   $urlGenerator,
        private LoggerInterface $logger,
    ) {
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Returns all Tables the authenticated user can access.
     *
     * @return array<array{id:int,title:string,emoji:string}>
     * @throws \RuntimeException
     */
    public function listTables(): array {
        return $this->get('/tables');
    }

    /**
     * Returns full schema (columns + views) for a single table.
     *
     * @return array<string, mixed>
     * @throws \RuntimeException
     */
    public function getTableSchema(int $tableId): array {
        return $this->get("/tables/{$tableId}");
    }

    /**
     * Returns the columns defined for a table.
     *
     * @return array<array{id:int,title:string,type:string,subtype:string}>
     * @throws \RuntimeException
     */
    public function getColumns(int $tableId): array {
        return $this->get("/tables/{$tableId}/columns");
    }

    /**
     * Inserts a new row into a table.
     *
     * $data must be an array of column-value pairs:
     *   [['columnId' => 77, 'value' => '2024-10-29'], ...]
     *
     * @param array<array{columnId:int,value:mixed}> $data
     * @return array<string, mixed> Created row
     * @throws \RuntimeException
     */
    public function insertRow(int $tableId, array $data): array {
        return $this->post("/tables/{$tableId}/rows", ['data' => $data]);
    }

    /**
     * Searches rows in a table using a simple filter.
     *
     * $filter elements follow the Tables API format:
     *   [['columnId' => 77, 'operator' => 'is-equal', 'value' => '...']]
     *
     * @param array<array{columnId:int,operator:string,value:mixed}> $filter
     * @return array<array<string, mixed>>
     * @throws \RuntimeException
     */
    public function searchRows(int $tableId, array $filter, int $limit = 50, int $offset = 0): array {
        return $this->post("/tables/{$tableId}/rows/search", [
            'limit'   => $limit,
            'offset'  => $offset,
            'filter'  => $filter,
        ]);
    }

    // -------------------------------------------------------------------------
    // Private HTTP helpers
    // -------------------------------------------------------------------------

    /**
     * Performs a GET request to the Tables API.
     *
     * @return array<mixed>
     * @throws \RuntimeException
     */
    private function get(string $path): array {
        $url = $this->buildUrl($path);
        $this->logger->debug('[webtrack/tables] GET {url}', ['url' => $url]);
        try {
            $response = $this->clientService->newClient()->get($url, $this->defaultOptions());
            return $this->decode($response->getBody());
        } catch (\Throwable $e) {
            throw new \RuntimeException("Tables GET {$path} failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Performs a POST request to the Tables API.
     *
     * @param array<mixed> $body
     * @return array<mixed>
     * @throws \RuntimeException
     */
    private function post(string $path, array $body): array {
        $url = $this->buildUrl($path);
        $this->logger->debug('[webtrack/tables] POST {url}', ['url' => $url]);
        try {
            $response = $this->clientService->newClient()->post($url, array_merge(
                $this->defaultOptions(),
                ['json' => $body],
            ));
            return $this->decode($response->getBody());
        } catch (\Throwable $e) {
            throw new \RuntimeException("Tables POST {$path} failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Builds the full API URL for a given path.
     */
    private function buildUrl(string $path): string {
        if ($this->apiBase === '') {
            // Generate an internal URL to any app route; strip the path component
            // so we are left with just https://hostname (no trailing slash).
            $sample   = $this->urlGenerator->getAbsoluteURL('/');
            $this->apiBase = rtrim($sample, '/') . '/apps/tables/api/1';
        }
        return $this->apiBase . $path;
    }

    /**
     * Default HTTP options shared by all requests.
     *
     * @return array<string, mixed>
     */
    private function defaultOptions(): array {
        return [
            'timeout'         => 15,
            'connect_timeout' => 5,
            'headers'         => [
                'Accept'       => 'application/json',
                'OCS-APIREQUEST' => 'true',
            ],
        ];
    }

    /**
     * JSON-decodes a response body; throws on failure.
     *
     * @return array<mixed>
     * @throws \RuntimeException
     */
    private function decode(mixed $body): array {
        $raw = is_string($body) ? $body : (string) $body;
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Tables API returned non-JSON response: ' . substr($raw, 0, 200));
        }
        return $decoded;
    }
}
