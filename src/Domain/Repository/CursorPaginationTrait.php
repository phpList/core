<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Repository;

trait CursorPaginationTrait
{
    /**
     * Provides cursor-based pagination using an integer ID.
     *
     * @param int $lastId The last seen ID to paginate after
     * @param int $limit  Max number of results to return
     * @return array
     */
    public function getAfterId(int $lastId, int $limit): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.id > :lastId')
            ->setParameter('lastId', $lastId)
            ->orderBy('e.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
