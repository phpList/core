<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Repository;

use Doctrine\ORM\QueryBuilder;
use PhpList\Core\Domain\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use RuntimeException;

trait CursorPaginationTrait
{
    abstract protected function createQueryBuilder(string $alias): QueryBuilder;

    abstract protected function getAlias(): string;

    /**
     * Apply cursor-based pagination to a QueryBuilder.
     *
     * @param QueryBuilder $queryBuilder
     * @param int $lastId Last seen ID
     * @param int $limit Max results
     * @return array
     */
    public function getAfterId(QueryBuilder $queryBuilder, int $lastId, int $limit): array
    {
        $alias = $this->getAlias();

        return $queryBuilder
            ->andWhere("$alias.id > :lastId")
            ->setParameter('lastId', $lastId)
            ->orderBy("$alias.id", 'ASC')
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
        $alias = $this->getAlias();
        $queryBuilder = $this->createQueryBuilder($alias);

        if ($filter === null) {
            return $this->getAfterId($queryBuilder, $lastId, $limit);
        }

        throw new RuntimeException('Filter method not implemented');
    }
}
