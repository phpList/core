<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Repository;

use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;

class SubscriberAttributeDefinitionRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;

    public function findOneByName(string $name): ?SubscriberAttributeDefinition
    {
        return $this->findOneBy(['name' => $name]);
    }

    public function existsByTableName(string $tableName): bool
    {
        return (bool) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.tableName IS NOT NULL')
            ->andWhere('s.tableName = :tableName')
            ->setParameter('tableName', $tableName)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
