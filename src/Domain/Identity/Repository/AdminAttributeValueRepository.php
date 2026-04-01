<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Repository;

use InvalidArgumentException;
use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Common\Model\PaginatedResult;
use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Identity\Model\AdminAttributeValue;
use PhpList\Core\Domain\Identity\Model\Filter\AdminAttributeValueFilter;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeValue;

class AdminAttributeValueRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    public function findOneByAdminIdAndAttributeId(int $adminId, int $definitionId): ?AdminAttributeValue
    {
        return $this->createQueryBuilder('aav')
            ->join('aav.administrator', 'admin')
            ->join('aav.attributeDefinition', 'attr')
            ->where('admin.id = :adminId')
            ->andWhere('attr.id = :attributeId')
            ->setParameter('adminId', $adminId)
            ->setParameter('attributeId', $definitionId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return PaginatedResult<SubscriberAttributeValue>
     * @throws InvalidArgumentException
     */
    public function getFilteredAfterId(FilterRequestInterface $filter): PaginatedResult
    {
        $lastId = $filter->getLastId();
        $limit = $filter->getLimit();
        if (!$filter instanceof AdminAttributeValueFilter) {
            throw new InvalidArgumentException('Expected AdminAttributeValueFilter.');
        }
        $queryBuilder = $this->createQueryBuilder('aav')
            ->join('aav.administrator', 'a')
            ->join('aav.attributeDefinition', 'ad');

        if ($filter->getAdminId() !== null) {
            $queryBuilder->andWhere('a.id = :adminId')
                ->setParameter('adminId', $filter->getAdminId());
        }

        $countQb = clone $queryBuilder;
        $total = (int) $countQb
            ->select('COUNT(DISTINCT ad.id)')
            ->getQuery()
            ->getSingleScalarResult();

        /** @var list<SubscriberAttributeValue> $items */
        $items = $queryBuilder
            ->andWhere('ad.id > :lastId')
            ->setParameter('lastId', $lastId)
            ->orderBy('ad.id', 'ASC')
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
