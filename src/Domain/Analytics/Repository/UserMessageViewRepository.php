<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Analytics\Repository;

use DateTimeInterface;
use Doctrine\DBAL\Exception;
use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;

class UserMessageViewRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;

    public function countByMessageId(int $messageId): int
    {
        return (int) $this->createQueryBuilder('umv')
            ->select('COUNT(umv.id)')
            ->where('umv.messageId = :messageId')
            ->setParameter('messageId', $messageId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function uniqueByMessageId(int $messageId): int
    {
        return (int) $this->createQueryBuilder('umv')
            ->select('COUNT(DISTINCT umv.ip)')
            ->where('umv.messageId = :messageId')
            ->setParameter('messageId', $messageId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Counts views between two dates.
     *
     * @param DateTimeInterface $start
     * @param DateTimeInterface $end
     * @return int
     */
    public function countBetween(DateTimeInterface $start, DateTimeInterface $end): int
    {
        return (int) $this->createQueryBuilder('umv')
            ->select('COUNT(umv.id)')
            ->where('umv.viewed >= :start')
            ->andWhere('umv.viewed <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string,int> counts keyed by 'Y-m-d'
     * @throws Exception
     */
    public function countGroupedByDay(DateTimeInterface $start, DateTimeInterface $end): array
    {
        $connection = $this->getEntityManager()->getConnection();
        $table = $this->getClassMetadata()->getTableName();

        $sql = sprintf(
            'SELECT DATE(viewed) AS day, COUNT(*) AS cnt FROM %s WHERE viewed >= :start AND viewed <= :end'
            . ' GROUP BY DATE(viewed)',
            $table
        );

        $rows = $connection->executeQuery($sql, [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
        ])->fetchAllAssociative();

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['day']] = (int) $row['cnt'];
        }
        return $result;
    }

    /**
     * @param int[] $messageIds
     * @return array<int,int> view counts keyed by message id
     */
    public function countByMessageIds(array $messageIds): array
    {
        if (empty($messageIds)) {
            return [];
        }

        $rows = $this->createQueryBuilder('umv')
            ->select('umv.messageId AS messageId, COUNT(umv.id) AS cnt')
            ->where('umv.messageId IN (:ids)')
            ->setParameter('ids', $messageIds)
            ->groupBy('umv.messageId')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['messageId']] = (int) $row['cnt'];
        }
        return $result;
    }
}
