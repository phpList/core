<?php

namespace PhpList\Core\Domain\Repository\Interfaces;

namespace PhpList\Core\Domain\Repository\Interfaces;

use Doctrine\ORM\QueryBuilder;
use PhpList\Core\Domain\Filter\FilterRequestInterface;

interface PaginatableRepositoryInterface
{
    public function getAfterId(QueryBuilder $queryBuilder, int $lastId, int $limit): array;
    public function getFilteredAfterId(int $lastId, int $limit, ?FilterRequestInterface $filter = null): array;
    public function count(): int;
}
