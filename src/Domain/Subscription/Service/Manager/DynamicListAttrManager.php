<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Manager;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use PhpList\Core\Domain\Subscription\Model\Dto\DynamicListAttrDto;
use PhpList\Core\Domain\Subscription\Repository\DynamicListAttrRepository;
use RuntimeException;
use Throwable;

class DynamicListAttrManager
{
    private string $prefix;

    public function __construct(
        private readonly DynamicListAttrRepository $dynamicListAttrRepository,
        private readonly Connection $connection,
        string $dbPrefix = 'phplist_',
        string $dynamicListTablePrefix = 'listattr_',
    ) {
        $this->prefix = $dbPrefix . $dynamicListTablePrefix;
    }

    /**
     * Seed options into the just-created options table.
     *
     * @param string $listTable logical table (without global prefix)
     * @param DynamicListAttrDto[]  $rawOptions
     * @throws Exception|Throwable
     */
    public function insertOptions(string $listTable, array $rawOptions, array &$usedOrders = []): array
    {
        $result = [];
        if (empty($rawOptions)) {
            return $result;
        }

        $fullTable = $this->prefix . $listTable;

        $options = [];
        $index = 0;
        foreach ($rawOptions as $opt) {
            if ($opt->listOrder !== null) {
                $order = $opt->listOrder;
            } else {
                do {
                    $index++;
                } while (isset($usedOrders[$index]));

                $order = $index;
                $usedOrders[$order] = true;
            }
            $options[] = new DynamicListAttrDto(id: null, name: $opt->name, listOrder: $order);
        }

        $seen = [];
        $unique = [];
        foreach ($options as $opt) {
            $lowercaseName = mb_strtolower($opt->name);
            if (isset($seen[$lowercaseName])) {
                continue;
            }
            $seen[$lowercaseName] = true;
            $unique[] = $opt;
        }

        if ($unique === []) {
            return $result;
        }

        $this->connection->beginTransaction();
        try {
            return $this->createFromDtos($fullTable, $unique);
        } catch (Throwable $e) {
            $this->connection->rollBack();
            return [];
        }
    }

    public function syncOptions(string $getTableName, array $options): array
    {
        $fullTable = $this->prefix . $getTableName;
        $result = [];
        $usedOrders = $this->getSetListOrders($options);
        [$incomingById, $incomingNew] = $this->splitOptionsSet($options, $usedOrders);

        $this->connection->beginTransaction();
        try {
            [$currentById, $currentByName] = $this->splitCurrentSet($fullTable);

            // 1) Updates for items with id
            $result = array_merge($result, $this->updateRowsById($incomingById, $currentById, $fullTable));

            foreach ($incomingNew as $dto) {
                if (isset($currentByName[$dto->name])) {
                    $this->connection->update(
                        $fullTable,
                        ['name' => $dto->name, 'listorder' => $dto->listOrder],
                        ['id' => $dto->id],
                    );
                    $result[] = $dto;
                    unset($incomingNew[$dto->name]);
                }
            }

            // 2) Inserts for new items (no id)
            $result = array_merge($result, $this->insertOptions($getTableName, $incomingNew, $usedOrders));

            // 3) Prune: rows not present in input
            $missing = array_diff_key($currentByName, $incomingNew);
            foreach ($missing as $row) {
                // This row is not in input → consider removal
                if (!$this->optionHasReferences($getTableName, $row->id)) {
                    $this->connection->delete($fullTable, ['id' => $row->id], ['integer']);
                } else {
                    $result[] = $row;
                }
            }

            $this->connection->commit();
        } catch (Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }

        return $result;
    }

    private function optionHasReferences(string $listTable, int $id): bool
    {
        $fullTable = $this->prefix . $listTable;
        $stmt = $this->connection->executeQuery(
            'SELECT COUNT(*) FROM ' . $fullTable . ' WHERE id = :id',
            ['id' => $id]
        );
        return (bool)$stmt->fetchOne();
    }

    private function getSetListOrders(array $options): array
    {
        $nonNullListOrders = array_values(
            array_filter(
                array_map(fn($opt) => $opt->listOrder ?? null, $options),
                fn($order) => $order !== null
            )
        );
        return array_flip($nonNullListOrders);
    }

    private function splitOptionsSet(array $options, array &$usedOrders): array
    {
        $incomingById = [];
        $incomingNew = [];
        $index = 0;

        foreach ($options as $opt) {
            if ($opt->listOrder !== null) {
                $order = $opt->listOrder;
            } else {
                do {
                    $index++;
                } while (isset($usedOrders[$index]));

                $order = $index;
                $usedOrders[$order] = true;
            }
            if ($opt->id !== null) {
                $incomingById[(int)$opt->id] = new DynamicListAttrDto(
                    id: $opt->id,
                    name: $opt->name,
                    listOrder: $order,
                );
            } else {
                $key = mb_strtolower($opt->name);
                if (!isset($incomingNew[$key])) {
                    $incomingNew[$key] = new DynamicListAttrDto(id: null, name: $opt->name, listOrder: $order);
                }
            }
        }

        return [$incomingById, $incomingNew];
    }

    private function splitCurrentSet(string $fullTable): array
    {
        $currentById = [];
        $currentByName = [];

        $rows = $this->dynamicListAttrRepository->getAll($fullTable);
        foreach ($rows as $listAttrDto) {
            $currentById[$listAttrDto->id] = $listAttrDto;
            $currentByName[mb_strtolower($listAttrDto->name)] = $listAttrDto;
        }

        return [$currentById, $currentByName];
    }

    private function updateRowsById(array $incomingById, array $currentById, string $fullTable): array
    {
        $result = [];

        $insertSql = 'INSERT INTO ' . $fullTable . ' (name, listorder) VALUES (:name, :listOrder)';
        $insertStmt = $this->connection->prepare($insertSql);

        foreach ($incomingById as $dto) {
            if (!isset($currentById[$dto->id])) {
                $insertStmt->bindValue('name', $dto->name, ParameterType::STRING);
                $insertStmt->bindValue('listorder', $dto->listOrder, ParameterType::INTEGER);
                $insertStmt->executeStatement();

                $result[] = new DynamicListAttrDto(
                    id: (int) $this->connection->lastInsertId(),
                    name: $dto->name,
                    listOrder: $dto->listOrder
                );
            } else {
                $cur = $currentById[$dto->id];
                $updates = [];
                if ($cur->name !== $dto->name) {
                    $nameExists = $this->dynamicListAttrRepository->existsByName($fullTable, $dto->name);
                    if ($nameExists) {
                        throw new RuntimeException('Option name ' . $dto->name . ' already exists.');
                    }
                    $updates['name'] = $dto->name;
                }
                if ($cur->listOrder !== $dto->listOrder) {
                    $updates['listorder'] = $dto->listOrder;
                }

                if ($updates) {
                    $this->connection->update($fullTable, $updates, ['id' => $dto->id]);
                }
                $result[] = $dto;
            }
        }

        return $result;
    }

    private function createFromDtos(string $fullTable, array $unique): array
    {
        $sql = 'INSERT INTO ' . $fullTable . ' (name, listorder) VALUES (:name, :listOrder)';
        $stmt = $this->connection->prepare($sql);

        $result = [];
        foreach ($unique as $opt) {
            $stmt->bindValue('name', $opt->name, ParameterType::STRING);
            $stmt->bindValue('listOrder', $opt->listOrder, ParameterType::INTEGER);
            $stmt->executeStatement();

            $inserted = new DynamicListAttrDto(
                id: (int) $this->connection->lastInsertId(),
                name: $opt->name,
                listOrder: $opt->listOrder
            );

            $result[] = $inserted;
        }
        $this->connection->commit();

        return $result;
    }
}
