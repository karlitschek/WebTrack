<?php

declare(strict_types=1);

namespace OCA\WebTrack\AppInfo;

use OCA\WebTrack\Dashboard\RecentFindsWidget;
use OCA\WebTrack\Notification\Notifier;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
    public const APP_ID = 'webtrack';

    public function __construct() {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerNotifierService(Notifier::class);
        $context->registerDashboardWidget(RecentFindsWidget::class);
    }

    public function boot(IBootContext $context): void {
    }
}
