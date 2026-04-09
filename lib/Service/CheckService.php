<?php

declare(strict_types=1);

namespace OCA\WebTrack\Service;

use OCP\Http\Client\IClientService;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

class CheckService {
    public function __construct(
        private IClientService $clientService,
        private IL10N $l,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Fetches the URL and returns the body as a string.
     *
     * @throws \RuntimeException on HTTP error or connection failure
     */
    public function fetch(string $url): string {
        $parsed = parse_url($url);
        $scheme = strtolower($parsed['scheme'] ?? '');
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new \RuntimeException($this->l->t('Only http and https URLs are allowed'));
        }

        try {
            $response = $this->clientService->newClient()->get($url, [
                'timeout'         => 30,
                'connect_timeout' => 10,
                'allow_redirects' => ['max' => 5],
                'headers'         => [
                    'User-Agent' => 'Nextcloud/WebTrack (+https://nextcloud.com)',
                    'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ],
            ]);
            return (string) $response->getBody();
        } catch (\Throwable $e) {
            $this->logger->debug('[webtrack] fetch failed for {url}: {err}', [
                'url' => $url,
                'err' => $e->getMessage(),
            ]);
            throw new \RuntimeException($this->l->t('Fetch failed: %s', [$e->getMessage()]), 0, $e);
        }
    }

    /**
     * Returns plain text from HTML (strips tags, decodes entities).
     */
    public function htmlToText(string $html): string {
        // Remove scripts and styles
        $text = preg_replace('/<(script|style)[^>]*>.*?<\/\1>/si', ' ', $html);
        // Strip remaining tags
        $text = strip_tags($text ?? $html);
        // Decode HTML entities
        $text = html_entity_decode($text ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return $text ?? '';
    }
}
