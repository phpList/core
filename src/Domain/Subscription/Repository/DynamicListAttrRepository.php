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

    public function transactional(callable $callback): mixed
    {
        $this->connection->beginTransaction();
        try {
            $result = $callback();
            $this->connection->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
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

    public function insertOne(string $listTable, DynamicListAttrDto $dto): DynamicListAttrDto
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $listTable)) {
            throw new InvalidArgumentException('Invalid list table');
        }
        $table = $this->fullTablePrefix . $listTable;
        $this->connection->insert($table, [
            'name' => $dto->name,
            'listorder' => $dto->listOrder ?? 0,
        ]);
        $id = (int)$this->connection->lastInsertId();
        return new DynamicListAttrDto(id: $id, name: $dto->name, listOrder: $dto->listOrder ?? 0);
    }

    /**
     * @param DynamicListAttrDto[] $dtos
     * @return DynamicListAttrDto[]
     */
    public function insertMany(string $listTable, array $dtos): array
    {
        $result = [];
        foreach ($dtos as $dto) {
            $result[] = $this->insertOne($listTable, $dto);
        }
        return $result;
    }

    public function updateById(string $listTable, int $id, array $updates): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $listTable)) {
            throw new InvalidArgumentException('Invalid list table');
        }
        $table = $this->fullTablePrefix . $listTable;
        $this->connection->update($table, $updates, ['id' => $id]);
    }

    public function deleteById(string $listTable, int $id): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $listTable)) {
            throw new InvalidArgumentException('Invalid list table');
        }
        $table = $this->fullTablePrefix . $listTable;
        $this->connection->delete($table, ['id' => $id], ['integer']);
    }

    public function existsById(string $listTable, int $id): bool
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $listTable)) {
            throw new InvalidArgumentException('Invalid list table');
        }
        $table = $this->fullTablePrefix . $listTable;
        $stmt = $this->connection->executeQuery('SELECT COUNT(*) FROM ' . $table . ' WHERE id = :id', ['id' => $id]);
        return (bool)$stmt->fetchOne();
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
