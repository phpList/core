<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Repository;

use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;

class ListMessageRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;

    /** @return int[] */
    public function getListIdsByMessageId(int $messageId): array
    {
        return $this->createQueryBuilder('lm')
            ->select('lm.listId')
            ->where('lm.messageId = :messageId')
            ->setParameter('messageId', $messageId)
            ->getQuery()
            ->getSingleColumnResult();
    }
}
