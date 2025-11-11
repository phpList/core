<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service\Manager;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use PhpList\Core\Domain\Subscription\Model\AttributeTypeEnum;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeDefinitionRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\DynamicListAttrTablesManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DynamicListAttrTablesManagerTest extends TestCase
{
    private SubscriberAttributeDefinitionRepository&MockObject $definitionRepo;
    private AbstractSchemaManager&MockObject $schema;

    protected function setUp(): void
    {
        $this->definitionRepo = $this->createMock(SubscriberAttributeDefinitionRepository::class);
        $this->schema = $this->getMockBuilder(AbstractSchemaManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['tablesExist', 'createTable'])
            ->getMockForAbstractClass();
    }

    private function makeManager(): DynamicListAttrTablesManager
    {
        return new DynamicListAttrTablesManager(
            definitionRepository: $this->definitionRepo,
            schemaManager: $this->schema,
            dbPrefix: 'phplist_',
            dynamicListTablePrefix: 'listattr_'
        );
    }

    public function testResolveTableNameReturnsNullWhenTypeIsNullOrUnsupported(): void
    {
        $manager = $this->makeManager();

        $this->assertNull($manager->resolveTableName('My Name', null));

        // For unsupported type (e.g., Text) should be null
        $this->assertNull($manager->resolveTableName('My Name', AttributeTypeEnum::Text));
    }

    public function testResolveTableNameSnakeCasesAndEnsuresUniqueness(): void
    {
        // First two candidates exist, third is unique
        $this->definitionRepo
            ->method('existsByTableName')
            ->willReturnOnConsecutiveCalls(true, true, false);

        $manager = $this->makeManager();
        $name = $manager->resolveTableName('Fancy Label', AttributeTypeEnum::Select);

        // "Fancy Label" -> "fancy_label" with numeric suffix appended after collisions
        $this->assertSame('fancy_label2', $name);
    }

    public function testCreateOptionsTableIfNotExistsCreatesOnlyWhenMissing(): void
    {
        // First call: table does not exist -> createTable called, second call: exists -> no create
        $this->schema->method('tablesExist')->willReturnOnConsecutiveCalls(false, true);
        $this->schema->expects($this->once())->method('createTable');

        $manager = $this->makeManager();
        $manager->createOptionsTableIfNotExists('sizes');
        $manager->createOptionsTableIfNotExists('sizes');
        $this->assertTrue(true);
    }
}
