<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Repository\Identity;

use PhpList\Core\Domain\Repository\AbstractRepository;
use PhpList\Core\Domain\Repository\CursorPaginationTrait;

class AdminAttributeRepository extends AbstractRepository
{
    use CursorPaginationTrait;
}
