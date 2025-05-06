<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Repository;

use PhpList\Core\Domain\Model\Dto\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use RuntimeException;

trait CursorPaginationTrait
{
    /**
     * @param int $lastId Last seen ID
     * @param int $limit Max results
     * @return array
     */
    public function getAfterId(int $lastId, int $limit): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.id > :lastId')
            ->setParameter('lastId', $lastId)
            ->orderBy('e.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get filtered + paginated messages for a given owner and status.
     *
     * @return DomainModel[]
     * @throws RuntimeException
     */
    public function getFilteredAfterId(int $lastId, int $limit, ?FilterRequestInterface $filter = null): array
    {
        if ($filter === null) {
            return $this->getAfterId($lastId, $limit);
        }

        throw new RuntimeException('Filter method not implemented');
    }
}
