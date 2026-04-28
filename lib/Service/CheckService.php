<?php

declare(strict_types=1);

namespace OCA\WebTrack\Service;

use OCP\Http\Client\IClientService;
use OCP\IL10N;
use OCP\Security\IRemoteHostValidator;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;

class CheckService {
    public function __construct(
        private IClientService $clientService,
        private IRemoteHostValidator $remoteHostValidator,
        private IL10N $l,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Fetches the URL and returns the body as a string.
     *
     * SSRF protection: only http/https schemes are accepted, and the host is
     * validated against IRemoteHostValidator both for the initial URL and for
     * every redirect hop, so user-supplied URLs cannot be used to probe
     * loopback / link-local / private-range targets through the Nextcloud
     * server.  Admins who explicitly set `allow_local_remote_servers=true`
     * intentionally opt out, and the validator honours that flag.
     *
     * @throws \RuntimeException on HTTP error, connection failure, or
     *                            disallowed scheme/host (initial or redirect)
     */
    public function fetch(string $url): string {
        $this->assertSafeUrl($url);

        try {
            $response = $this->clientService->newClient()->get($url, [
                'timeout'         => 30,
                'connect_timeout' => 10,
                'allow_redirects' => [
                    'max'         => 5,
                    'protocols'   => ['http', 'https'],
                    'on_redirect' => function (
                        RequestInterface $request,
                        ResponseInterface $response,
                        UriInterface $uri,
                    ): void {
                        $this->assertSafeUrl((string) $uri);
                    },
                ],
                'headers' => [
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
     * Rejects URLs whose scheme is not http(s) or whose host resolves to a
     * loopback, link-local, or private-network address.  Used both for the
     * initial fetch URL and inside the redirect callback.
     *
     * @throws \RuntimeException when the URL must not be fetched
     */
    private function assertSafeUrl(string $url): void {
        $parsed = parse_url($url);
        if ($parsed === false) {
            throw new \RuntimeException($this->l->t('Invalid URL'));
        }
        $scheme = strtolower($parsed['scheme'] ?? '');
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new \RuntimeException($this->l->t('Only http and https URLs are allowed'));
        }
        $host = (string) ($parsed['host'] ?? '');
        if ($host === '' || !$this->remoteHostValidator->isValid($host)) {
            throw new \RuntimeException($this->l->t('Host "%s" is not allowed', [$host]));
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
