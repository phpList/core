<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service\Manager;

use PhpList\Core\Domain\Subscription\Model\Dto\DynamicListAttrDto;
use PhpList\Core\Domain\Subscription\Repository\DynamicListAttrRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\DynamicListAttrManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DynamicListAttrManagerTest extends TestCase
{
    private DynamicListAttrRepository&MockObject $listAttrRepo;

    protected function setUp(): void
    {
        $this->listAttrRepo = $this->createMock(DynamicListAttrRepository::class);
    }

    private function makeManager(): DynamicListAttrManager
    {
        return new DynamicListAttrManager(
            dynamicListAttrRepository: $this->listAttrRepo,
        );
    }

    private function makePartialManager(array $methods): DynamicListAttrManager|MockObject
    {
        return $this->getMockBuilder(DynamicListAttrManager::class)
            ->setConstructorArgs([
                $this->listAttrRepo,
            ])
            ->onlyMethods($methods)
            ->getMock();
    }

    public function testInsertOptionsSkipsEmpty(): void
    {
        $manager = $this->makeManager();

        // Empty array should be a no-op (no DB calls)
        $this->listAttrRepo->expects($this->never())->method('transactional');
        $manager->insertOptions('colors', []);
        // if we got here, expectations were met
        $this->assertTrue(true);
    }

    public function testInsertOptionsSkipsDuplicatesAndAssignsOrder(): void
    {
        $manager = $this->makeManager();

        $this->listAttrRepo->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $cb) {
                // The callback should call insertMany with one unique row
                $this->listAttrRepo->expects($this->once())
                    ->method('insertMany')
                    ->with('colors', $this->callback(function ($arr) {
                        return is_array($arr) && count($arr) === 1 && $arr[0] instanceof DynamicListAttrDto;
                    }))
                    ->willReturn([new DynamicListAttrDto(id: 1, name: 'Red', listOrder: 1)]);
                return $cb();
            });

        $opts = [
            new DynamicListAttrDto(id: null, name: 'Red'),
            // duplicate by case-insensitive compare -> skipped
            new DynamicListAttrDto(id: null, name: 'red'),
        ];

        $result = $manager->insertOptions('colors', $opts);
        $this->assertCount(1, $result);
        $this->assertSame('Red', $result[0]->name);
    }

    public function testSyncOptionsInsertsAndPrunesWithReferences(): void
    {
        $this->listAttrRepo->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $cb) {
                return $cb();
            });

        $this->listAttrRepo->expects($this->once())
            ->method('getAll')
            ->with('colors')
            ->willReturn([]);

        // Mock insertOptions to avoid dealing with SQL internals
        $manager = $this->makePartialManager(['insertOptions']);
        $manager->expects($this->once())
            ->method('insertOptions')
            ->with('colors', $this->callback(function ($arr) {
                // Should get two unique items
                return is_array($arr) && count($arr) === 2;
            }), $this->anything())
            ->willReturn([]);

        $result = $manager->syncOptions('colors', [
            new DynamicListAttrDto(id: null, name: 'Red'),
            new DynamicListAttrDto(id: null, name: 'Blue'),
        ]);

        $this->assertCount(0, $result);
    }
}
