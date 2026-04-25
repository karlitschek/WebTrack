<?php

declare(strict_types=1);

namespace OCA\WebTrack\Controller;

use OCA\WebTrack\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;

class SettingsController extends Controller {

    public function __construct(
        string $appName,
        IRequest $request,
        private IConfig $config,
        private ?string $userId,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function show(): JSONResponse {
        $uid    = $this->userId ?? '';
        $apiKey = $this->config->getUserValue($uid, Application::APP_ID, 'youtube_api_key', '');
        return new JSONResponse([
            'defaultTalkRoomToken' => $this->config->getUserValue($uid, Application::APP_ID, 'default_talk_room', ''),
            // Return a masked key so the frontend can show "configured" without exposing the key
            'youtubeApiKeySet'     => $apiKey !== '',
        ]);
    }

    #[NoAdminRequired]
    public function save(): JSONResponse {
        $uid   = $this->userId ?? '';
        $token = trim($this->request->getParam('defaultTalkRoomToken', ''));
        $this->config->setUserValue($uid, Application::APP_ID, 'default_talk_room', $token);

        // Only update the API key if the request actually includes it (empty = clear it)
        if ($this->request->offsetExists('youtubeApiKey')) {
            $key = trim($this->request->getParam('youtubeApiKey', ''));
            $this->config->setUserValue($uid, Application::APP_ID, 'youtube_api_key', $key);
        }

        return new JSONResponse(['ok' => true]);
    }
}
