<?php

namespace PhpList\Core\Domain\Repository\Interfaces;

namespace PhpList\Core\Domain\Common\Repository\Interfaces;

use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;

interface PaginatableRepositoryInterface
{
    public function getFilteredAfterId(int $lastId, int $limit, ?FilterRequestInterface $filter = null): array;
    public function count(): int;
}
