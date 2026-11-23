<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Core\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use PhpList\Core\Core\Doctrine\IndexPrefixLengthListener;
use PHPUnit\Framework\TestCase;

class IndexPrefixLengthListenerTest extends TestCase
{
    private function schema(): Schema
    {
        $schema = new Schema();
        $table = $schema->createTable('phplist_urlcache');
        $table->addColumn('id', 'integer');
        $table->addColumn('url', 'string', ['length' => 2083]);
        $table->addColumn('hash', 'string', ['length' => 32]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['url'], 'phplist_urlcache_urlindex', [], ['lengths' => [255]]);
        $table->addUniqueIndex(['url'], 'phplist_urlcache_urlunique', ['lengths' => [255]]);
        $table->addIndex(['hash'], 'phplist_urlcache_hashindex');

        return $schema;
    }

    private function dispatch(Schema $schema, AbstractPlatform $platform): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        (new IndexPrefixLengthListener())->postGenerateSchema(new GenerateSchemaEventArgs($entityManager, $schema));
    }

    public function testKeepsPrefixLengthsOnMySql(): void
    {
        $schema = $this->schema();

        $this->dispatch($schema, new MySQLPlatform());

        $table = $schema->getTable('phplist_urlcache');
        $this->assertSame([255], $table->getIndex('phplist_urlcache_urlindex')->getOption('lengths'));
        $this->assertSame([255], $table->getIndex('phplist_urlcache_urlunique')->getOption('lengths'));
    }

    public function testDropsPrefixLengthsOnOtherPlatforms(): void
    {
        $schema = $this->schema();

        $this->dispatch($schema, new PostgreSQLPlatform());

        $table = $schema->getTable('phplist_urlcache');
        $index = $table->getIndex('phplist_urlcache_urlindex');
        $this->assertFalse($index->hasOption('lengths'));
        $this->assertSame(['url'], $index->getColumns());
        $this->assertFalse($index->isUnique());

        $unique = $table->getIndex('phplist_urlcache_urlunique');
        $this->assertFalse($unique->hasOption('lengths'));
        $this->assertTrue($unique->isUnique());

        $this->assertTrue($table->hasIndex('phplist_urlcache_hashindex'));
        $this->assertTrue($table->getIndex('primary')->isPrimary());
    }
}
