<?php

namespace PhpList\Core\Domain\Repository\Interfaces;

namespace PhpList\Core\Domain\Common\Repository\Interfaces;

use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Common\Model\PaginatedResult;

interface PaginatableRepositoryInterface
{
    public function getFilteredAfterId(
        int $lastId,
        int $limit,
        ?FilterRequestInterface $filter = null
    ): PaginatedResult;
}
