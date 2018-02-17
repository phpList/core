<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Domain\Repository\Messaging;

use PhpList\PhpList4\Domain\Model\Identity\Administrator;
use PhpList\PhpList4\Domain\Model\Messaging\SubscriberList;
use PhpList\PhpList4\Domain\Repository\AbstractRepository;

/**
 * Repository for SubscriberList models.
 *
 * @method SubscriberList[] findByOwner(Administrator $owner)
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class SubscriberListRepository extends AbstractRepository
{
}
