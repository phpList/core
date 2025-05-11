<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Repository;

use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberList;
use PhpList\Core\Domain\Subscription\Model\Subscription;

/**
 * Repository for Subscription models.
 *
 * @method Subscription[] findBySubscriber(Subscriber $subscriber)
 * @method Subscription[] findBySubscriberList(SubscriberList $list)
 *
 * @author Oliver Klee <oliver@phplist.com>
 * @author Tatevik Grigoryan <tatevik@phplist.com>
 */
class SubscriptionRepository extends AbstractRepository
{
    public function findOneBySubscriberListAndSubscriber(SubscriberList $list, Subscriber $subscriber): ?Subscription
    {
        return $this->findOneBy(
            [
                'subscriberList' => $list,
                'subscriber' => $subscriber,
            ]
        );
    }

    public function findOneBySubscriberEmailAndListId(int $listId, string $email): ?Subscription
    {
        return $this->createQueryBuilder('subscription')
            ->join('subscription.subscriber', 'subscriber')
            ->join('subscription.subscriberList', 'list')
            ->where('subscriber.email = :email')
            ->andWhere('list.id = :listId')
            ->setParameter('email', $email)
            ->setParameter('listId', $listId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
