<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Repository\Messaging;

use PhpList\Core\Domain\Repository\AbstractRepository;
use PhpList\Core\Domain\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Repository\Interfaces\PaginatableRepositoryInterface;

class MessageDataRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;
}
