<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Analytics\Repository;

use DateTimeInterface;
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
}
