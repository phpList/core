<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Repository;

use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Identity\Model\AdminAttributeValue;

class AdminAttributeValueRepository extends AbstractRepository
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
}
