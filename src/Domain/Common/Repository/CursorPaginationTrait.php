<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\Repository;

use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Common\Model\PaginatedResult;

trait CursorPaginationTrait
{
    /**
     * @param int $lastId Last seen ID
     * @param int $limit Max results
     */
    public function getAfterId(int $lastId, int $limit): PaginatedResult
    {
        $queryBuilder = $this->createQueryBuilder('e');

        $countQb = clone $queryBuilder;
        $total = (int) $countQb
            ->select('COUNT(DISTINCT e.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $queryBuilder
            ->andWhere('e.id > :lastId')
            ->setParameter('lastId', $lastId)
            ->orderBy('e.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return new PaginatedResult(
            items: $items,
            total: $total,
            limit: $limit,
            lastId: $lastId,
        );
    }

    public function getFilteredAfterId(FilterRequestInterface $filter): PaginatedResult
    {
        return $this->getAfterId($filter->getLastId(), $filter->getLimit());
    }
}
