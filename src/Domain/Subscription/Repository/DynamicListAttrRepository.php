<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use InvalidArgumentException;
use PDO;
use PhpList\Core\Domain\Subscription\Model\Dto\DynamicListAttrDto;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class DynamicListAttrRepository
{
    private string $fullTablePrefix;

    public function __construct(
        private readonly Connection $connection,
        private readonly DenormalizerInterface $serializer,
        string $dbPrefix = 'phplist_',
        string $dynamicListTablePrefix = 'listattr_',
    ) {
        $this->fullTablePrefix = $dbPrefix . $dynamicListTablePrefix;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return DynamicListAttrDto[]
     * @throws ExceptionInterface
     */
    private function denormalizeAll(array $rows): array
    {
        return array_map(
            fn(array $row) => $this->serializer->denormalize($row, DynamicListAttrDto::class),
            $rows
        );
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

        $table = $this->fullTablePrefix . $listTable;

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

        $table = $this->fullTablePrefix . $listTable;

        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('name')
            ->from($table)
            ->where('id = :id')
            ->setParameter('id', $id);

        $val = $queryBuilder->executeQuery()->fetchOne();

        return $val === false ? null : (string) $val;
    }

    public function existsByName(string $listTable, string $name): bool
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $listTable)) {
            throw new InvalidArgumentException('Invalid list table');
        }

        $table = $this->fullTablePrefix . $listTable;

        try {
            $sql = 'SELECT 1 FROM ' . $table . ' WHERE LOWER(name) = LOWER(?) LIMIT 1';
            $result = $this->connection->fetchOne($sql, [$name], [PDO::PARAM_STR]);

            return $result !== false;
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function getAll(string $listTable): array
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $listTable)) {
            throw new InvalidArgumentException('Invalid list table');
        }

        $table = $this->fullTablePrefix . $listTable;

        $rows = $this->connection->createQueryBuilder()
            ->select('id', 'name', 'listorder')
            ->from($table)
            ->executeQuery()
            ->fetchAllAssociative();

        return $this->denormalizeAll($rows);
    }
}
