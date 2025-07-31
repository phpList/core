<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Repository;

use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Messaging\Model\ListMessage;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Subscription\Model\SubscriberList;

class ListMessageRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;

    public function isMessageAssociatedWithList(Message $message, SubscriberList $list): bool
    {
        $count = $this->createQueryBuilder('lm')
            ->select('COUNT(lm.id)')
            ->where('lm.message = :message')
            ->andWhere('lm.subscriberList = :list')
            ->setParameter('message', $message)
            ->setParameter('list', $list)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function getByMessageAndList(Message $message, SubscriberList $list): ?ListMessage
    {
        return $this->createQueryBuilder('lm')
            ->where('lm.message = :message')
            ->andWhere('lm.subscriberList = :list')
            ->setParameter('message', $message)
            ->setParameter('list', $list)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function removeAllListAssociationsForMessage(Message $message): void
    {
        $this->createQueryBuilder('lm')
            ->delete()
            ->where('lm.message = :message')
            ->setParameter('message', $message)
            ->getQuery()
            ->execute();
    }
}
