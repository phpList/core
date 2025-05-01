<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Repository\Configuration;

use PhpList\Core\Domain\Repository\AbstractRepository;
use PhpList\Core\Domain\Repository\CursorPaginationTrait;

class UrlCacheRepository extends AbstractRepository
{
    use CursorPaginationTrait;
}
