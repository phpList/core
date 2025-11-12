<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\MessageHandler;

use Doctrine\DBAL\Exception\TableExistsException;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Table;
use InvalidArgumentException;
use PhpList\Core\Domain\Subscription\Message\DynamicTableMessage;
use PhpList\Core\Domain\Subscription\MessageHandler\DynamicTableMessageHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PhpList\Core\Tests\Support\DBAL\FakeDriverException;

class DynamicTableMessageHandlerTest extends TestCase
{
    private AbstractSchemaManager&MockObject $schemaManager;

    protected function setUp(): void
    {
        $this->schemaManager = $this->createMock(AbstractSchemaManager::class);
    }

    public function testInvokeCreatesTableWhenNotExists(): void
    {
        $tableName = 'phplist_listattr_sizes';
        $message = new DynamicTableMessage($tableName);

        $capturedTable = null;

        $this->schemaManager
            ->expects($this->once())
            ->method('tablesExist')
            ->with([$tableName])
            ->willReturn(false);

        $this->schemaManager
            ->expects($this->once())
            ->method('createTable')
            ->with($this->callback(function (Table $table) use (&$capturedTable, $tableName) {
                $capturedTable = $table;
                // Basic checks
                $this->assertSame($tableName, $table->getName());
                $this->assertTrue($table->hasColumn('id'));
                $this->assertTrue($table->hasColumn('name'));
                $this->assertTrue($table->hasColumn('listorder'));

                // id column
                $idCol = $table->getColumn('id');
                $this->assertSame('integer', $idCol->getType()->getName());
                $this->assertTrue($idCol->getAutoincrement());
                $this->assertTrue($idCol->getNotnull());

                // name column
                $nameCol = $table->getColumn('name');
                $this->assertSame('string', $nameCol->getType()->getName());
                $this->assertSame(255, $nameCol->getLength());
                $this->assertFalse($nameCol->getNotnull());

                // listorder column
                $orderCol = $table->getColumn('listorder');
                $this->assertSame('integer', $orderCol->getType()->getName());
                $this->assertFalse($orderCol->getNotnull());
                $this->assertSame(0, $orderCol->getDefault());

                // Primary key
                $this->assertSame(['id'], $table->getPrimaryKey()?->getColumns());

                // Unique index on name
                $indexName = 'uniq_' . $tableName . '_name';
                $this->assertTrue($table->hasIndex($indexName));
                $idx = $table->getIndex($indexName);
                $this->assertTrue($idx->isUnique());
                $this->assertSame(['name'], $idx->getColumns());

                return true;
            }))
            ->willReturnCallback(function (Table $table) {
                // no-op; we just want the assertions in the callback
            });

        $handler = new DynamicTableMessageHandler($this->schemaManager);
        $handler($message);

        $this->assertInstanceOf(Table::class, $capturedTable);
    }

    public function testInvokeDoesNothingWhenTableAlreadyExists(): void
    {
        $tableName = 'phplist_listattr_sizes';
        $message = new DynamicTableMessage($tableName);

        $this->schemaManager
            ->expects($this->once())
            ->method('tablesExist')
            ->with([$tableName])
            ->willReturn(true);

        $this->schemaManager
            ->expects($this->never())
            ->method('createTable');

        $handler = new DynamicTableMessageHandler($this->schemaManager);
        $handler($message);
        // reached without creating a table
        $this->assertTrue(true);
    }

    public function testInvokeThrowsForInvalidTableName(): void
    {
        $invalidName = 'invalid-name!';
        $message = new DynamicTableMessage($invalidName);

        // tablesExist is consulted before validating the name
        $this->schemaManager
            ->expects($this->once())
            ->method('tablesExist')
            ->with([$invalidName])
            ->willReturn(false);

        $handler = new DynamicTableMessageHandler($this->schemaManager);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid list table name: ' . $invalidName);
        $handler($message);
        $this->assertTrue(true);
    }

    public function testInvokeSwallowsTableExistsRace(): void
    {
        $tableName = 'phplist_listattr_colors';
        $message = new DynamicTableMessage($tableName);

        $this->schemaManager
            ->expects($this->once())
            ->method('tablesExist')
            ->with([$tableName])
            ->willReturn(false);

        $this->schemaManager
            ->expects($this->once())
            ->method('createTable')
            ->willThrowException(new TableExistsException(
                new FakeDriverException('already exists', '42P07'),
                null
            ));

        $handler = new DynamicTableMessageHandler($this->schemaManager);

        // Should not throw despite the TableExistsException
        $handler($message);
        $this->assertTrue(true);
    }
}
