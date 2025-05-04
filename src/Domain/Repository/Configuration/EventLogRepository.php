<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Repository\Configuration;

use PhpList\Core\Domain\Repository\AbstractRepository;
use PhpList\Core\Domain\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Repository\Interfaces\PaginatableRepositoryInterface;

class EventLogRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;
}
