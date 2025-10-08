<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;

class DynamicListAttrRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $prefix = 'phplist_'
    ) {
    }

    /**
     * @return list<string>
     * @throws InvalidArgumentException
     */
    public function fetchOptionNames(string $listTable, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        if (!preg_match('/^[A-Za-z0-9_]+$/', $listTable)) {
            throw new InvalidArgumentException('Invalid list table');
        }

        $table = $this->prefix . 'listattr_' . $listTable;

        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('name')
            ->from($table)
            ->where('id IN (:ids)')
            ->setParameter('ids', array_map('intval', $ids), ArrayParameterType::INTEGER);

        return $queryBuilder->executeQuery()->fetchFirstColumn();
    }

    public function fetchSingleOptionName(string $listTable, int $id): ?string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $listTable)) {
            throw new InvalidArgumentException('Invalid list table');
        }

        $table = $this->prefix . 'listattr_' . $listTable;

        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('name')
            ->from($table)
            ->where('id = :id')
            ->setParameter('id', $id);

        $val = $queryBuilder->executeQuery()->fetchOne();

        return $val === false ? null : (string) $val;
    }
}
