<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Repository;

use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Messaging\Model\ListMessage;

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

    /** @return int[] */
    public function getMessageIdsByListId(int $listId): array
    {
        return $this->createQueryBuilder('lm')
            ->select('lm.messageId')
            ->where('lm.listId = :listId')
            ->setParameter('listId', $listId)
            ->getQuery()
            ->getSingleColumnResult();
    }

    public function isMessageAssociatedWithList(int $messageId, int $listId): bool
    {
        $count = $this->createQueryBuilder('lm')
            ->select('COUNT(lm.id)')
            ->where('lm.messageId = :messageId')
            ->andWhere('lm.listId = :listId')
            ->setParameter('messageId', $messageId)
            ->setParameter('listId', $listId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function getByMessageIdAndListId(int $messageId, int $listId): ?ListMessage
    {
        return $this->createQueryBuilder('lm')
            ->where('lm.messageId = :messageId')
            ->andWhere('lm.listId = :listId')
            ->setParameter('messageId', $messageId)
            ->setParameter('listId', $listId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function removeAllListAssociationsForMessage(int $messageId): void
    {
        $this->createQueryBuilder('lm')
            ->delete()
            ->where('lm.messageId = :messageId')
            ->setParameter('messageId', $messageId)
            ->getQuery()
            ->execute();
    }
}
