<?php

declare(strict_types=1);

namespace OCA\WebTrack\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int    getId()
 * @method int    getMonitorId()
 * @method void   setMonitorId(int $id)
 * @method string getSeenIds()
 * @method void   setSeenIds(string $json)
 * @method string getUpdatedAt()
 * @method void   setUpdatedAt(string $ts)
 */
class FeedState extends Entity {
    protected int $monitorId = 0;
    protected string $seenIds = '[]';
    protected string $updatedAt = '';

    public function __construct() {
        $this->addType('id', 'integer');
        $this->addType('monitorId', 'integer');
    }
}
