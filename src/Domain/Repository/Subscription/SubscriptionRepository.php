<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Domain\Repository\Subscription;

use PhpList\PhpList4\Domain\Model\Messaging\SubscriberList;
use PhpList\PhpList4\Domain\Model\Subscription\Subscriber;
use PhpList\PhpList4\Domain\Model\Subscription\Subscription;
use PhpList\PhpList4\Domain\Repository\AbstractRepository;

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
    public function findOneBySubscriberListAndSubscriber(SubscriberList $list, Subscriber $subscriber)
    {
        return $this->findOneBy(
            [
                'subscriberList' => $list,
                'subscriber' => $subscriber,
            ]
        );
    }
}
