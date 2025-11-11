<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Manager;

use Doctrine\DBAL\Exception\TableExistsException;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Table;
use InvalidArgumentException;
use PhpList\Core\Domain\Subscription\Model\AttributeTypeEnum;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeDefinitionRepository;
use function Symfony\Component\String\u;

class DynamicListAttrTablesManager
{
    private string $prefix;

    public function __construct(
        private readonly SubscriberAttributeDefinitionRepository $definitionRepository,
        private readonly AbstractSchemaManager $schemaManager,
        string $dbPrefix = 'phplist_',
        string $dynamicListTablePrefix = 'listattr_',
    ) {
        $this->prefix = $dbPrefix . $dynamicListTablePrefix;
    }

    public function resolveTableName(string $name, ?AttributeTypeEnum $type): ?string
    {
        if ($type === null) {
            return null;
        }

        if (!$type->isMultiValued()) {
            return null;
        }

        $base = u($name)->snake()->toString();
        $candidate = $base;
        $index = 1;
        while ($this->definitionRepository->existsByTableName($candidate)) {
            $suffix = $index;
            $candidate = $base . $suffix;
            $index++;
        }

        return $candidate;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function createOptionsTableIfNotExists(string $listTable): void
    {
        $fullTableName = $this->prefix . $listTable;

        if ($this->schemaManager->tablesExist([$fullTableName])) {
            return;
        }

        if (!preg_match('/^[A-Za-z0-9_]+$/', $listTable)) {
            throw new InvalidArgumentException('Invalid list table name: ' . $listTable);
        }

        $table = new Table($fullTableName);
        $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $table->addColumn('name', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('listorder', 'integer', ['notnull' => false, 'default' => 0]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['name'], 'uniq_' . $fullTableName . '_name');

        try {
            $this->schemaManager->createTable($table);
        } catch (TableExistsException $e) {
            // Table was created by a concurrent process or a previous test run.
            // Since this method is idempotent by contract, swallow the exception.
            return;
        }
    }
}
