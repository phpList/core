<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Repository;

use InvalidArgumentException;
use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Identity\Model\AdminAttributeValue;
use PhpList\Core\Domain\Identity\Model\Filter\AdminAttributeValueFilter;

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
     * @return AdminAttributeValue[]
     * @throws InvalidArgumentException
     */
    public function getFilteredAfterId(int $lastId, int $limit, ?FilterRequestInterface $filter = null): array
    {
        if (!$filter instanceof AdminAttributeValueFilter) {
            throw new InvalidArgumentException('Expected AdminAttributeValueFilter.');
        }
        $query = $this->createQueryBuilder('aav')
            ->join('aav.administrator', 'a')
            ->join('aav.attributeDefinition', 'ad')
            ->where('ad.id > :lastId')
            ->setParameter('lastId', $lastId);

        if ($filter->getAdminId() !== null) {
            $query->andWhere('a.id = :adminId')
                ->setParameter('adminId', $filter->getAdminId());
        }
        return $query->orderBy('ad.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
