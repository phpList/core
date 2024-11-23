<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Repository\Subscription;

use PhpList\Core\Domain\Model\Messaging\SubscriberList;
use PhpList\Core\Domain\Model\Subscription\Subscriber;
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
}
