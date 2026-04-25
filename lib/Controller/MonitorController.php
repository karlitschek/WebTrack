<?php

declare(strict_types=1);

namespace OCA\WebTrack\Controller;

use OCA\WebTrack\Service\CheckService;
use OCA\WebTrack\Service\FeedService;
use OCA\WebTrack\Service\MonitorService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;

class MonitorController extends Controller {

    public function __construct(
        string $appName,
        IRequest $request,
        private MonitorService $monitorService,
        private CheckService $checkService,
        private FeedService $feedService,
        private IL10N $l,
        private ?string $userId,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function index(): JSONResponse {
        $monitors = $this->monitorService->listForUser($this->userId ?? '');
        return new JSONResponse(array_map(fn($m) => $m->jsonSerialize(), $monitors));
    }

    #[NoAdminRequired]
    public function show(int $id): JSONResponse {
        try {
            $monitor = $this->monitorService->getForUser($id, $this->userId ?? '');
            return new JSONResponse($monitor->jsonSerialize());
        } catch (DoesNotExistException) {
            return new JSONResponse(['error' => $this->l->t('Not found')], Http::STATUS_NOT_FOUND);
        }
    }

    #[NoAdminRequired]
    public function create(): JSONResponse {
        $data = $this->request->getParams();
        $errors = $this->validate($data);
        if ($errors) {
            return new JSONResponse(['errors' => $errors], Http::STATUS_UNPROCESSABLE_ENTITY);
        }
        try {
            $monitor = $this->monitorService->create($this->userId ?? '', $data);
        } catch (\OverflowException $e) {
            return new JSONResponse(['errors' => [$e->getMessage()]], Http::STATUS_UNPROCESSABLE_ENTITY);
        }
        return new JSONResponse($monitor->jsonSerialize(), Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function update(int $id): JSONResponse {
        try {
            $data   = $this->request->getParams();
            $errors = $this->validate($data);
            if ($errors) {
                return new JSONResponse(['errors' => $errors], Http::STATUS_UNPROCESSABLE_ENTITY);
            }
            $monitor = $this->monitorService->update($id, $this->userId ?? '', $data);
            return new JSONResponse($monitor->jsonSerialize());
        } catch (DoesNotExistException) {
            return new JSONResponse(['error' => $this->l->t('Not found')], Http::STATUS_NOT_FOUND);
        }
    }

    #[NoAdminRequired]
    public function destroy(int $id): JSONResponse {
        try {
            $this->monitorService->delete($id, $this->userId ?? '');
            return new JSONResponse(null, Http::STATUS_NO_CONTENT);
        } catch (DoesNotExistException) {
            return new JSONResponse(['error' => $this->l->t('Not found')], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * Runs a check for the given monitor immediately, ignoring the interval gate.
     * Returns the updated monitor after the check completes.
     */
    #[NoAdminRequired]
    public function checkNow(int $id): JSONResponse {
        try {
            $monitor = $this->monitorService->getForUser($id, $this->userId ?? '');
            $this->monitorService->runCheckForMonitor($monitor);
            // Reload from DB to pick up status/lastCheckAt changes
            $monitor = $this->monitorService->getForUser($id, $this->userId ?? '');
            return new JSONResponse($monitor->jsonSerialize());
        } catch (DoesNotExistException) {
            return new JSONResponse(['error' => $this->l->t('Not found')], Http::STATUS_NOT_FOUND);
        } catch (\Throwable $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_GATEWAY);
        }
    }

    #[NoAdminRequired]
    public function pause(int $id): JSONResponse {
        try {
            $pause   = (bool) ($this->request->getParam('pause', true));
            $monitor = $this->monitorService->setPaused($id, $this->userId ?? '', $pause);
            return new JSONResponse($monitor->jsonSerialize());
        } catch (DoesNotExistException) {
            return new JSONResponse(['error' => $this->l->t('Not found')], Http::STATUS_NOT_FOUND);
        }
    }

    #[NoAdminRequired]
    public function test(): JSONResponse {
        $url = trim($this->request->getParam('url', ''));
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return new JSONResponse(['error' => $this->l->t('Invalid URL')], Http::STATUS_UNPROCESSABLE_ENTITY);
        }
        try {
            $content  = $this->checkService->fetch($url);
            $isFeed   = $this->feedService->isFeed($content);
            $text     = $isFeed ? strip_tags($content) : $this->checkService->htmlToText($content);
            $preview  = mb_substr(preg_replace('/\s+/', ' ', trim($text)) ?? '', 0, 500);
            return new JSONResponse(['ok' => true, 'isFeed' => $isFeed, 'preview' => $preview]);
        } catch (\Throwable $e) {
            return new JSONResponse(['ok' => false, 'error' => $e->getMessage()], Http::STATUS_BAD_GATEWAY);
        }
    }

    private function validate(array $data): array {
        $errors = [];
        if (empty(trim($data['name'] ?? '')))    { $errors[] = $this->l->t('Name is required'); }
        // Auto-URL sources build their feed URL from keyword/channel — no manual URL needed.
        $sourceType = $data['sourceType'] ?? 'custom';
        if ($sourceType === 'custom') {
            if (empty(trim($data['url'] ?? '')))     { $errors[] = $this->l->t('URL is required'); }
            elseif (!filter_var(trim($data['url']), FILTER_VALIDATE_URL)) { $errors[] = $this->l->t('URL is invalid'); }
        }
        if ($sourceType === 'youtube' && empty(trim($data['youtubeChannelId'] ?? ''))) {
            $errors[] = $this->l->t('Channel ID is required');
        }
        if (empty(trim($data['keyword'] ?? ''))) { $errors[] = $this->l->t('Keyword is required'); }
        elseif (!empty($data['useRegex'])) {
            $pattern = trim($data['keyword']);
            if (!preg_match('#^/.+/[a-z]*$#s', $pattern)) {
                $pattern = '/' . $pattern . '/iu';
            }
            if (@preg_match($pattern, '') === false) {
                $errors[] = $this->l->t('Invalid regular expression');
            }
        }
        return $errors;
    }
}
