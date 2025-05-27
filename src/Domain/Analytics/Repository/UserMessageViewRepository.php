<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Analytics\Repository;

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
            ->where('umv.message_id = :messageId')
            ->setParameter('messageId', $messageId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
