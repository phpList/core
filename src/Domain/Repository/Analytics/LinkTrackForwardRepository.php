<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Repository\Analytics;

use PhpList\Core\Domain\Repository\AbstractRepository;
use PhpList\Core\Domain\Repository\CursorPaginationTrait;

class LinkTrackForwardRepository extends AbstractRepository
{
    use CursorPaginationTrait;
}
