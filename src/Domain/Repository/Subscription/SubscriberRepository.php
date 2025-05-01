<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Repository\Subscription;

use PhpList\Core\Domain\Model\Subscription\Subscriber;
use PhpList\Core\Domain\Repository\AbstractRepository;
use PhpList\Core\Domain\Repository\CursorPaginationTrait;

/**
 * Repository for Subscriber models.
 *
 * @method Subscriber|null findOneByEmail(string $email)
 *
 * @author Oliver Klee <oliver@phplist.com>
 * @author Tatevik Grigoryan <tatevik@phplist.com>
 */
class SubscriberRepository extends AbstractRepository
{
    use CursorPaginationTrait;

    public function findSubscribersBySubscribedList(int $listId): ?Subscriber
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.subscriptions', 'subscription')
            ->innerJoin('subscription.subscriberList', 'list')
            ->where('list.id = :listId')
            ->setParameter('listId', $listId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return Subscriber[] */
    public function getSubscribersBySubscribedListId(int $listId): array
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.subscriptions', 'subscription')
            ->innerJoin('subscription.subscriberList', 'list')
            ->where('list.id = :listId')
            ->setParameter('listId', $listId)
            ->getQuery()
            ->getResult();
    }

    public function findSubscriberWithSubscriptions(int $id): ?Subscriber
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.subscriptions', 'subscription')
            ->innerJoin('subscription.subscriberList', 'list')
            ->addSelect('subscription')
            ->addSelect('list')
            ->where('s.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
