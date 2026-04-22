<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Repository;

use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Common\Model\PaginatedResult;
use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Messaging\Model\Template;

class TemplateRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;

    public function findOneById(int $id): ?Template
    {
        return $this->findOneBy(['id' => $id]);
    }

    /**
     * @return PaginatedResult<Template>
     */
    public function getFilteredAfterId(FilterRequestInterface $filter): PaginatedResult
    {
        $lastId = $filter->getLastId();
        $limit = $filter->getLimit();

        $queryBuilder = $this->createQueryBuilder('t')
            ->leftJoin('t.images', 'ti');

        $countQb = clone $queryBuilder;
        $total = (int) $countQb
            ->select('COUNT(DISTINCT t.id)')
            ->getQuery()
            ->getSingleScalarResult();

        /** @var list<Template> $items */
        $items = $queryBuilder
            ->andWhere('t.id > :lastId')
            ->setParameter('lastId', $lastId)
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
}
