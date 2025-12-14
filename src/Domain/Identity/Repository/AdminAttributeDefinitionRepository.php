<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Repository;

use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Identity\Model\AdminAttributeDefinition;
use PhpList\Core\Domain\Identity\Model\Administrator;

class AdminAttributeDefinitionRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;

    public function findOneByName(string $name): ?AdminAttributeDefinition
    {
        return $this->findOneBy(['name' => $name]);
    }

    /** @return array<int, array{value: mixed, name: string}> */
    public function getForAdmin(Administrator $admin): array
    {
        return $this->createQueryBuilder('ad')
            ->select("COALESCE(aav.value, '') AS value", 'ad.name')
            ->leftJoin(
                'ad.attributeValues',
                'aav',
                'WITH',
                'aav.administrator = :admin'
            )
            ->setParameter('admin', $admin)
            ->getQuery()
            ->getResult();
    }

    public function getAllWIthEmptyValues(): array
    {
        return $this->createQueryBuilder('ad')
            ->select("ad.name AS name", "'' AS value")
            ->getQuery()
            ->getResult();
    }
}
