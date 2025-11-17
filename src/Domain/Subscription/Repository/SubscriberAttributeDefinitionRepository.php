<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Repository;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;

class SubscriberAttributeDefinitionRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;

    public function __construct(
        EntityManagerInterface $entityManager,
        private readonly DynamicListAttrRepository $dynamicListAttrRepository,
    ) {
        parent::__construct(
            $entityManager,
            $entityManager->getClassMetadata(SubscriberAttributeDefinition::class)
        );
    }

    /**
     * @param SubscriberAttributeDefinition[] $defs
     * @return SubscriberAttributeDefinition[]
     */
    private function hydrateOptionsForAll(array $defs): array
    {
        foreach ($defs as $def) {
            $this->hydrateOptions($def);
        }
        return $defs;
    }

    private function hydrateOptions(SubscriberAttributeDefinition $def): void
    {
        $table = $def->getTableName();
        if ($table) {
            $options = $this->dynamicListAttrRepository->getAll($table);
            $def->setOptions($options);
        }
    }

    public function getAfterId(int $lastId, int $limit): array
    {
        $result = $this->createQueryBuilder('e')
            ->andWhere('e.id > :lastId')
            ->setParameter('lastId', $lastId)
            ->orderBy('e.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->hydrateOptionsForAll($result);
    }

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
