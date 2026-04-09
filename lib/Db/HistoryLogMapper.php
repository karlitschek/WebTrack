<?php

declare(strict_types=1);

namespace OCA\WebTrack\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<HistoryLog>
 */
class HistoryLogMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'wn_history', HistoryLog::class);
    }

    /** @return HistoryLog[] */
    public function findByMonitor(int $monitorId, int $limit = 50, int $offset = 0): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('monitor_id', $qb->createNamedParameter($monitorId, IQueryBuilder::PARAM_INT)))
            ->orderBy('created_at', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);
        return $this->findEntities($qb);
    }

    public function deleteByMonitor(int $monitorId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('monitor_id', $qb->createNamedParameter($monitorId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }

    public function purgeOlderThan(string $cutoffIso): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->lt('created_at', $qb->createNamedParameter($cutoffIso)));
        $qb->executeStatement();
    }

    /** @return array<int, array{id: int, monitor_id: int, monitor_name: string, keyword: string, snippet: string|null, created_at: string}> */
    public function findLatestFoundByUser(string $userId, int $limit = 10): array {
        $qb = $this->db->getQueryBuilder();
        // Use createFunction() for table-aliased columns so Nextcloud's
        // column-name quoting helper does not mangle the expressions.
        $qb->select(
                $qb->createFunction('h.id'),
                $qb->createFunction('h.monitor_id'),
                $qb->createFunction('h.snippet'),
                $qb->createFunction('h.created_at'),
                $qb->createFunction('m.name AS monitor_name'),
                $qb->createFunction('m.keyword AS keyword')
            )
            ->from($this->getTableName(), 'h')
            ->innerJoin('h', 'wn_monitors', 'm', $qb->expr()->eq(
                $qb->createFunction('h.monitor_id'),
                $qb->createFunction('m.id')
            ))
            ->where($qb->expr()->eq('h.user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->eq('h.event', $qb->createNamedParameter('found')))
            ->orderBy($qb->createFunction('h.created_at'), 'DESC')
            ->setMaxResults($limit);

        $result = $qb->executeQuery();
        $rows   = $result->fetchAllAssociative();
        $result->closeCursor();
        return $rows;
    }
}
