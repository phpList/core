<?php
declare(strict_types=1);

namespace PhpList\Core\Domain\Repository\Subscription;

use PhpList\Core\Domain\Model\Subscription\Subscriber;
use PhpList\Core\Domain\Repository\AbstractRepository;

/**
 * Repository for Subscriber models.
 *
 * @method Subscriber findOneByEmail(string $email)
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class SubscriberRepository extends AbstractRepository
{
}
