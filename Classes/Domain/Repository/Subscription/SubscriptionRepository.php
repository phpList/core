<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Domain\Repository\Subscription;

use PhpList\PhpList4\Domain\Model\Subscription\Subscription;
use PhpList\PhpList4\Domain\Repository\AbstractRepository;

/**
 * Repository for Subscription models.
 *
 * @method Subscription[] findBySubscriber(int $subscriberId)
 * @method Subscription[] findBySubscriberList(int $listId)
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class SubscriptionRepository extends AbstractRepository
{
}
