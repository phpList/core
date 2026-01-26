<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Repository;

use DateTimeInterface;
use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Subscription\Model\Subscriber;

class UserMessageForwardRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;

    public function getCountByMessageId(int $messageId): int
    {
        return (int) $this->createQueryBuilder('umf')
            ->select('COUNT(umf.id)')
            ->where('umf.messageId = :messageId')
            ->setParameter('messageId', $messageId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getCountByUserSince(Subscriber $user, DateTimeInterface $cutoff): int
    {
        return (int) $this->createQueryBuilder('umf')
            ->select('COUNT(umf.id)')
            ->where('umf.user = :userId')
            ->andWhere('umf.status = :status')
            ->andWhere('umf.time >= :cutoff')
            ->setParameter('userId', $user->getId())
            ->setParameter('status', 'sent')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
