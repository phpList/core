<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Repository;

use PhpList\Core\Domain\Common\Model\PaginatedResult;
use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;

class SubscriberAttributeDefinitionRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;

    private ?DynamicListAttrRepository $dynamicListAttrRepository = null;

    public function setDynamicListAttrRepository(DynamicListAttrRepository $dynamicListAttrRepository): void
    {
        $this->dynamicListAttrRepository = $dynamicListAttrRepository;
    }

    /**
     * @param SubscriberAttributeDefinition[] $defs
     * @return SubscriberAttributeDefinition[]
     */
    private function hydrateOptionsForAll(array $defs): array
    {
        if ($this->dynamicListAttrRepository === null) {
            return $defs;
        }
        foreach ($defs as $def) {
            $this->hydrateOptions($def);
        }

        return $defs;
    }

    private function hydrateOptions(SubscriberAttributeDefinition $def): void
    {
        if ($this->dynamicListAttrRepository === null) {
            return;
        }
        $table = $def->getTableName();
        if ($table) {
            $options = $this->dynamicListAttrRepository->getAll($table);
            $def->setOptions($options);
        }
    }

    /** @return PaginatedResult<SubscriberAttributeDefinition>*/
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
            items: $this->hydrateOptionsForAll($items),
            total: $total,
            limit: $limit,
            lastId: $lastId,
        );
    }

    public function findOneByName(string $name): ?SubscriberAttributeDefinition
    {
        $def = $this->findOneBy(['name' => $name]);
        if ($def instanceof SubscriberAttributeDefinition) {
            $this->hydrateOptions($def);
        }

        return $def;
    }

    public function getByIds(array $ids): array
    {
        $defs = $this->findBy(['id' => $ids]);

        return $this->hydrateOptionsForAll($defs);
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
