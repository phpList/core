<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Repository\Analytics;

use PhpList\Core\Domain\Repository\AbstractRepository;
use PhpList\Core\Domain\Repository\CursorPaginationTrait;

class UserStatsRepository extends AbstractRepository
{
    use CursorPaginationTrait;
}
