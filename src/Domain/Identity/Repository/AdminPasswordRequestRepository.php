<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Repository;

use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;

class AdminPasswordRequestRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;
}
