<?php

declare(strict_types=1);

namespace OCA\WebTrack\Notification;

use OCA\WebTrack\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class Notifier implements INotifier {

    public function __construct(
        private IFactory      $l10nFactory,
        private IURLGenerator $urlGenerator,
    ) {
    }

    public function getID(): string {
        return Application::APP_ID;
    }

    public function getName(): string {
        $l = $this->l10nFactory->get(Application::APP_ID);
        return $l->t('WebTrack');
    }

    public function prepare(INotification $notification, string $languageCode): INotification {
        if ($notification->getApp() !== Application::APP_ID) {
            throw new \InvalidArgumentException('Unknown app');
        }

        $l = $this->l10nFactory->get(Application::APP_ID, $languageCode);
        $params = $notification->getSubjectParameters();

        switch ($notification->getSubject()) {
            case 'keyword_found':
                $notification->setParsedSubject(
                    $l->t('Keyword "%1$s" found on %2$s', [
                        $params['keyword'] ?? '',
                        $params['monitorName'] ?? '',
                    ])
                );
                if (!empty($params['snippet'])) {
                    $notification->setParsedMessage($params['snippet']);
                }
                break;

            case 'check_error':
                $notification->setParsedSubject(
                    $l->t('Monitor "%1$s" failed %2$s times', [
                        $params['monitorName'] ?? '',
                        (string) (int) ($params['errorCount'] ?? 0),
                    ])
                );
                if (!empty($params['errorMsg'])) {
                    $notification->setParsedMessage($params['errorMsg']);
                }
                break;

            default:
                throw new \InvalidArgumentException('Unknown subject');
        }

        $notification->setIcon(
            $this->urlGenerator->getAbsoluteURL(
                $this->urlGenerator->imagePath(Application::APP_ID, 'app.svg')
            )
        );

        $monitorId = (int) $notification->getObjectId();
        if ($monitorId > 0) {
            $notification->setLink(
                $this->urlGenerator->linkToRouteAbsolute(
                    'webtrack.page.index'
                ) . '#/monitors/' . $monitorId
            );
        }

        return $notification;
    }
}
