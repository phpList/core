<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Manager;

use PhpList\Core\Domain\Subscription\Model\Dto\DynamicListAttrDto;
use PhpList\Core\Domain\Subscription\Repository\DynamicListAttrRepository;
use RuntimeException;

class DynamicListAttrManager
{
    public function __construct(private readonly DynamicListAttrRepository $dynamicListAttrRepository,)
    {
    }

    /**
     * Seed options into the just-created options table.
     *
     * @param string $listTable logical table (without global prefix)
     * @param DynamicListAttrDto[]  $rawOptions
     */
    public function insertOptions(string $listTable, array $rawOptions, array &$usedOrders = []): array
    {
        $result = [];
        if (empty($rawOptions)) {
            return $result;
        }

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

        return $this->dynamicListAttrRepository->transactional(function () use ($listTable, $unique) {
            return $this->dynamicListAttrRepository->insertMany($listTable, $unique);
        });
    }

    public function syncOptions(string $getTableName, array $options): array
    {
        $result = [];
        $usedOrders = $this->getSetListOrders($options);
        [$incomingById, $incomingNew] = $this->splitOptionsSet($options, $usedOrders);

        return $this->dynamicListAttrRepository->transactional(function () use (
            $getTableName,
            $incomingById,
            $incomingNew,
            $usedOrders,
            &$result
        ) {
            [$currentById, $currentByName] = $this->splitCurrentSet($getTableName);

            // Track all lowercase names that should remain after sync (to avoid accidental pruning)
            $keptByLowerName = [];

            // 1) Updates for items with id and inserts for id-missing-but-present ones
            $result = array_merge(
                $result,
                $this->updateRowsById(
                    incomingById: $incomingById,
                    currentById: $currentById,
                    listTable: $getTableName
                )
            );
            foreach ($incomingById as $dto) {
                // Keep target names (case-insensitive)
                $keptByLowerName[mb_strtolower($dto->name)] = true;
            }

            // Handle incoming items without id but matching an existing row by case-insensitive name
            foreach ($incomingNew as $key => $dto) {
                // $key is already lowercase (set in splitOptionsSet)
                if (isset($currentByName[$key])) {
                    $existing = $currentByName[$key];
                    $this->dynamicListAttrRepository->updateById(
                        listTable: $getTableName,
                        id: (int)$existing->id,
                        updates: ['name' => $dto->name, 'listorder' => $dto->listOrder]
                    );
                    $result[] = new DynamicListAttrDto(id: $existing->id, name: $dto->name, listOrder: $dto->listOrder);
                    // Mark as kept and remove from incomingNew so it won't be re-inserted
                    $keptByLowerName[$key] = true;
                    unset($incomingNew[$key]);
                }
            }

            // 2) Inserts for truly new items (no id and no existing match)
            // Mark remaining new keys as kept, then insert them
            foreach (array_keys($incomingNew) as $lowerKey) {
                $keptByLowerName[$lowerKey] = true;
            }
            $result = array_merge(
                $result,
                $this->insertOptions(
                    listTable: $getTableName,
                    rawOptions: $incomingNew,
                    usedOrders: $usedOrders
                )
            );

            // 3) Prune: rows not present in the intended final set (case-insensitive)
            foreach ($currentByName as $lowerKey => $row) {
                if (!isset($keptByLowerName[$lowerKey])) {
                    // This row is not in desired input → consider removal
                    if (!$this->optionHasReferences($getTableName, (int)$row->id)) {
                        $this->dynamicListAttrRepository->deleteById($getTableName, (int)$row->id);
                    } else {
                        $result[] = $row;
                    }
                }
            }

            return $result;
        });
    }

    private function optionHasReferences(string $listTable, int $id): bool
    {
        return $this->dynamicListAttrRepository->existsById($listTable, $id);
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

    private function splitCurrentSet(string $listTable): array
    {
        $currentById = [];
        $currentByName = [];

        $rows = $this->dynamicListAttrRepository->getAll($listTable);
        foreach ($rows as $listAttrDto) {
            $currentById[$listAttrDto->id] = $listAttrDto;
            $currentByName[mb_strtolower($listAttrDto->name)] = $listAttrDto;
        }

        return [$currentById, $currentByName];
    }

    private function updateRowsById(array $incomingById, array $currentById, string $listTable): array
    {
        $result = [];

        foreach ($incomingById as $dto) {
            if (!isset($currentById[$dto->id])) {
                // Unexpected: incoming has id but the current table does not — insert a new row
                $inserted = $this->dynamicListAttrRepository->insertOne(
                    listTable: $listTable,
                    dto: new DynamicListAttrDto(id: null, name: $dto->name, listOrder: $dto->listOrder)
                );
                $result[] = $inserted;
            } else {
                $cur = $currentById[$dto->id];
                $updates = [];
                if ($cur->name !== $dto->name) {
                    $nameExists = $this->dynamicListAttrRepository->existsByName($listTable, $dto->name);
                    if ($nameExists) {
                        throw new RuntimeException('Option name ' . $dto->name . ' already exists.');
                    }
                    $updates['name'] = $dto->name;
                }
                if ($cur->listOrder !== $dto->listOrder) {
                    $updates['listorder'] = $dto->listOrder;
                }

                if ($updates) {
                    $this->dynamicListAttrRepository->updateById($listTable, (int)$dto->id, $updates);
                }
                $result[] = $dto;
            }
        }

        return $result;
    }
}
