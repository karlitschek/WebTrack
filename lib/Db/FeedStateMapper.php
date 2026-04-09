<?php

declare(strict_types=1);

namespace OCA\WebTrack\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<FeedState>
 */
class FeedStateMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'wn_feed_state', FeedState::class);
    }

    /** @throws DoesNotExistException */
    public function findByMonitor(int $monitorId): FeedState {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('monitor_id', $qb->createNamedParameter($monitorId, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    public function deleteByMonitor(int $monitorId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('monitor_id', $qb->createNamedParameter($monitorId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }
}
