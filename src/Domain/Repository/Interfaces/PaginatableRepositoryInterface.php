<?php

namespace PhpList\Core\Domain\Repository\Interfaces;

namespace PhpList\Core\Domain\Repository\Interfaces;

use PhpList\Core\Domain\Model\Dto\Filter\FilterRequestInterface;

interface PaginatableRepositoryInterface
{
    public function getFilteredAfterId(int $lastId, int $limit, ?FilterRequestInterface $filter = null): array;
    public function count(): int;
}
