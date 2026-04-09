<?php

declare(strict_types=1);

namespace OCA\WebTrack\BackgroundJob;

use OCA\WebTrack\Db\HistoryLogMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

class PurgeHistoryJob extends TimedJob {

    public function __construct(
        ITimeFactory $time,
        private HistoryLogMapper $historyMapper,
    ) {
        parent::__construct($time);
        $this->setInterval(86400); // once per day
    }

    protected function run($argument): void {
        $cutoff = (new \DateTimeImmutable())->modify('-100 days')->format(\DateTimeInterface::ATOM);
        $this->historyMapper->purgeOlderThan($cutoff);
    }
}
