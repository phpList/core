<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Repository;

use InvalidArgumentException;
use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Configuration\Model\Filter\EventLogFilter;
use PhpList\Core\Domain\Configuration\Model\EventLog;

class EventLogRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;

    /**
     * @return EventLog[]
     * @throws InvalidArgumentException
     */
    public function getFilteredAfterId(int $lastId, int $limit, ?FilterRequestInterface $filter = null): array
    {
        $queryBuilder = $this->createQueryBuilder('e')
            ->andWhere('e.id > :lastId')
            ->setParameter('lastId', $lastId)
            ->orderBy('e.id', 'ASC')
            ->setMaxResults($limit);

        if ($filter === null) {
            return $queryBuilder->getQuery()->getResult();
        }

        if (!$filter instanceof EventLogFilter) {
            throw new InvalidArgumentException('Expected EventLogFilter.');
        }

        if ($filter->getPage() !== null) {
            $queryBuilder->andWhere('e.page = :page')->setParameter('page', $filter->getPage());
        }
        if ($filter->getDateFrom() !== null) {
            $queryBuilder->andWhere('e.entered >= :dateFrom')->setParameter('dateFrom', $filter->getDateFrom());
        }
        if ($filter->getDateTo() !== null) {
            $queryBuilder->andWhere('e.entered <= :dateTo')->setParameter('dateTo', $filter->getDateTo());
        }

        return $queryBuilder->getQuery()->getResult();
    }
}
