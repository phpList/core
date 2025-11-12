<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\MessageHandler;

use Doctrine\DBAL\Exception\TableExistsException;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Table;
use InvalidArgumentException;
use PhpList\Core\Domain\Subscription\Message\DynamicTableMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class DynamicTableMessageHandler
{

    public function __construct(private readonly AbstractSchemaManager $schemaManager)
    {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function __invoke(DynamicTableMessage $message): void
    {
        if ($this->schemaManager->tablesExist([$message->getTableName()])) {
            return;
        }

        if (!preg_match('/^[A-Za-z0-9_]+$/', $message->getTableName())) {
            throw new InvalidArgumentException('Invalid list table name: ' . $message->getTableName());
        }

        $table = new Table($message->getTableName());
        $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $table->addColumn('name', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('listorder', 'integer', ['notnull' => false, 'default' => 0]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['name'], 'uniq_' . $message->getTableName() . '_name');

        try {
            $this->schemaManager->createTable($table);
        } catch (TableExistsException $e) {
            // Table was created by a concurrent process or a previous test run.
            // Since this method is idempotent by contract, swallow the exception.
            return;
        }
    }
}
