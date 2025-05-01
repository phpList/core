<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Repository\Subscription;

use PhpList\Core\Domain\Repository\AbstractRepository;
use PhpList\Core\Domain\Repository\CursorPaginationTrait;

class SubscriberPageRepository extends AbstractRepository
{
    use CursorPaginationTrait;
}
