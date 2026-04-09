<?php

declare(strict_types=1);

namespace OCA\WebTrack\BackgroundJob;

use OCA\WebTrack\Service\MonitorService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

class CheckMonitorsJob extends TimedJob {

    public function __construct(
        ITimeFactory $time,
        private MonitorService $monitorService,
    ) {
        parent::__construct($time);
        // Run every 5 minutes; per-monitor intervals are enforced inside MonitorService
        $this->setInterval(300);
        $this->setTimeSensitivity(self::TIME_SENSITIVE);
    }

    protected function run($argument): void {
        $this->monitorService->runAllChecks();
    }
}
