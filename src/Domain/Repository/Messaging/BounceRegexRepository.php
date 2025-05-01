<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Repository\Messaging;

use PhpList\Core\Domain\Repository\AbstractRepository;
use PhpList\Core\Domain\Repository\CursorPaginationTrait;

class BounceRegexRepository extends AbstractRepository
{
    use CursorPaginationTrait;
}
