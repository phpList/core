<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Repository\Subscription;

use PhpList\Core\Domain\Model\Subscription\Subscriber;
use PhpList\Core\Domain\Model\Subscription\SubscriberList;
use PhpList\Core\Domain\Model\Subscription\Subscription;
use PhpList\Core\Domain\Repository\AbstractRepository;

/**
 * Repository for Subscription models.
 *
 * @method Subscription[] findBySubscriber(Subscriber $subscriber)
 * @method Subscription[] findBySubscriberList(SubscriberList $list)
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class SubscriptionRepository extends AbstractRepository
{
    /**
     * @param SubscriberList $list
     * @param Subscriber $subscriber
     *
     * @return Subscription|null
     */
    public function findOneBySubscriberListAndSubscriber(SubscriberList $list, Subscriber $subscriber): ?Subscription
    {
        return $this->findOneBy(
            [
                'subscriberList' => $list,
                'subscriber' => $subscriber,
            ]
        );
    }

    /**
     * @param int $listId
     * @param string $email
     *
     * @return Subscription|null
     */
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
