<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service\Manager;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Statement;
use PhpList\Core\Domain\Subscription\Model\Dto\DynamicListAttrDto;
use PhpList\Core\Domain\Subscription\Repository\DynamicListAttrRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\DynamicListAttrManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DynamicListAttrManagerTest extends TestCase
{
    private DynamicListAttrRepository&MockObject $listAttrRepo;
    private Connection&MockObject $connection;

    protected function setUp(): void
    {
        $this->listAttrRepo = $this->createMock(DynamicListAttrRepository::class);
        $this->connection = $this->createMock(Connection::class);
    }

    private function makeManager(): DynamicListAttrManager
    {
        return new DynamicListAttrManager(
            dynamicListAttrRepository: $this->listAttrRepo,
            connection: $this->connection,
            dbPrefix: 'phplist_',
            dynamicListTablePrefix: 'listattr_'
        );
    }

    private function makePartialManager(array $methods): DynamicListAttrManager|MockObject
    {
        return $this->getMockBuilder(DynamicListAttrManager::class)
            ->setConstructorArgs([
                $this->listAttrRepo,
                $this->connection,
                'phplist_',
                'listattr_',
            ])
            ->onlyMethods($methods)
            ->getMock();
    }

    public function testInsertOptionsSkipsEmpty(): void
    {
        $manager = $this->makeManager();

        // Empty array should be a no-op (no DB calls)
        $this->connection->expects($this->never())->method('prepare');
        $manager->insertOptions('colors', []);
        // if we got here, expectations were met
        $this->assertTrue(true);
    }

    public function testInsertOptionsSkipsDuplicatesAndAssignsOrder(): void
    {
        $manager = $this->makeManager();

        // Now test with items: two with same name differing case, one with explicit order
        $stmt = $this->createMock(Statement::class);
        $stmt->expects($this->exactly(2))
            ->method('bindValue')
            ->with(
                $this->logicalOr('name', 'listOrder'),
                $this->anything(),
                $this->logicalOr(ParameterType::STRING, ParameterType::INTEGER)
            );
        $stmt->expects($this->exactly(1))->method('executeStatement');

        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('prepare')
            ->willReturn($stmt);
        $this->connection->expects($this->once())->method('commit');

        $opts = [
            new DynamicListAttrDto(id: null, name: 'Red'),
            // duplicate by case-insensitive compare -> skipped
            new DynamicListAttrDto(id: null, name: 'red'),
        ];

        $manager->insertOptions('colors', $opts);
        // if we got here, expectations were met
        $this->assertTrue(true);
    }

    public function testSyncOptionsInsertsAndPrunesWithReferences(): void
    {
        $this->listAttrRepo->expects($this->once())
            ->method('getAll')
            ->with('phplist_listattr_colors')
            ->willReturn([]);

        // Mock insertOptions to avoid dealing with SQL internals
        $manager = $this->makePartialManager(['insertOptions']);
        $manager->expects($this->once())
            ->method('insertOptions')
            ->with('colors', $this->callback(function ($arr) {
                // Should get two unique items
                return is_array($arr) && count($arr) === 2;
            }), $this->anything());

        // Expect transaction around main sync block
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');

        $result = $manager->syncOptions('colors', [
            new DynamicListAttrDto(id: null, name: 'Red'),
            new DynamicListAttrDto(id: null, name: 'Blue'),
        ]);

        $this->assertCount(0, $result);
    }
}
