<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service\Manager;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Subscription\Model\SubscribePage;
use PhpList\Core\Domain\Subscription\Model\SubscribePageData;
use PhpList\Core\Domain\Subscription\Repository\SubscriberPageDataRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberPageRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscribePageManager;
use PhpList\Core\Domain\Subscription\Service\SubscribePageConfigMigrationService;
use PhpList\Core\Domain\Subscription\Service\SubscribePagePlaceholderProcessor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SubscribePageManagerTest extends TestCase
{
    private SubscriberPageRepository|MockObject $pageRepository;
    private SubscriberPageDataRepository|MockObject $pageDataRepository;
    private SubscribePageConfigMigrationService|MockObject $configMigrationService;
    private EntityManagerInterface|MockObject $entityManager;
    private SubscribePageManager $manager;
    private SubscribePage|MockObject $page;

    protected function setUp(): void
    {
        $this->pageRepository = $this->createMock(SubscriberPageRepository::class);
        $this->pageDataRepository = $this->createMock(SubscriberPageDataRepository::class);
        $this->configMigrationService = $this->createMock(SubscribePageConfigMigrationService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->page = $this->createMock(SubscribePage::class);
        $this->page->method('getId')->willReturn(1);

        $this->manager = $this->createManager(true);
    }

    private function createManager(bool $parallelUseWithPhpList3): SubscribePageManager
    {
        return new SubscribePageManager(
            pageRepository: $this->pageRepository,
            pageDataRepository: $this->pageDataRepository,
            configMigrationService: $this->configMigrationService,
            entityManager: $this->entityManager,
            placeholderProcessor: $this->createMock(SubscribePagePlaceholderProcessor::class),
            parallelUseWithPhpList3: $parallelUseWithPhpList3,
        );
    }

    public function testFindPageReturnsPageFromRepositoryWithoutRefetchWhenMigrationMakesNoChanges(): void
    {
        $page = new SubscribePage();

        $this->pageRepository
            ->expects($this->once())
            ->method('findPageWithData')
            ->with(123)
            ->willReturn($page);

        $this->configMigrationService
            ->expects($this->once())
            ->method('copyToPageData')
            ->with($page)
            ->willReturn(false);

        $this->assertSame($page, $this->manager->findPage(123));
    }

    public function testFindPageRefetchesWhenMigrationChangesPageData(): void
    {
        $page = new SubscribePage();
        $refetchedPage = new SubscribePage();

        $findPageCalls = [];

        $this->pageRepository
            ->expects($this->exactly(2))
            ->method('findPageWithData')
            ->willReturnCallback(
                function (int $id) use (
                    &$findPageCalls,
                    $page,
                    $refetchedPage
                ): SubscribePage {
                    $findPageCalls[] = $id;

                    return count($findPageCalls) === 1
                        ? $page
                        : $refetchedPage;
                }
            );

        $this->configMigrationService
            ->expects($this->once())
            ->method('copyToPageData')
            ->with($page)
            ->willReturn(true);

        $result = $this->manager->findPage(123);

        $this->assertSame([123, 123], $findPageCalls);

        $this->assertSame($refetchedPage, $result);
    }

    public function testFindPageReturnsNullWhenMissing(): void
    {
        $this->pageRepository
            ->expects($this->once())
            ->method('findPageWithData')
            ->with(123)
            ->willReturn(null);

        $this->configMigrationService
            ->expects($this->never())
            ->method('copyToPageData');

        $this->assertNull($this->manager->findPage(123));
    }

    public function testFindPageSkipsConfigMigrationWhenFeatureIsDisabled(): void
    {
        $manager = $this->createManager(false);

        $this->pageRepository
            ->expects($this->once())
            ->method('findPageWithData')
            ->with(123)
            ->willReturn($this->page);

        $this->configMigrationService
            ->expects($this->never())
            ->method('copyToPageData');

        $this->assertSame($this->page, $manager->findPage(123));
    }

    public function testCreatePageCreatesAndSaves(): void
    {
        $owner = new Administrator();
        $this->pageRepository
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(SubscribePage::class));

        $page = $this->manager->createPage('My Page', true, $owner);

        $this->assertInstanceOf(SubscribePage::class, $page);
        $this->assertSame('My Page', $page->getTitle());
        $this->assertTrue($page->isActive());
        $this->assertSame($owner, $page->getOwner());
    }

    public function testUpdatePageUpdatesProvidedFieldsAndFlushes(): void
    {
        $originalOwner = new Administrator();
        $newOwner = new Administrator();
        $page = (new SubscribePage())
            ->setTitle('Old Title')
            ->setActive(false)
            ->setOwner($originalOwner);

        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $updated = $this->manager->updatePage($page, title: 'New Title', active: true, owner: $newOwner);

        $this->assertSame($page, $updated);
        $this->assertSame('New Title', $updated->getTitle());
        $this->assertTrue($updated->isActive());
        $this->assertSame($newOwner, $updated->getOwner());
    }

    public function testUpdatePageLeavesNullFieldsUntouched(): void
    {
        $owner = new Administrator();
        $page = (new SubscribePage())
            ->setTitle('Keep Title')
            ->setActive(true)
            ->setOwner($owner);

        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $updated = $this->manager->updatePage(page: $page, title: null, active: null, owner: null);

        $this->assertSame('Keep Title', $updated->getTitle());
        $this->assertTrue($updated->isActive());
        $this->assertSame($owner, $updated->getOwner());
    }

    public function testSetActiveSetsFlagButNDoesotFlush(): void
    {
        $page = (new SubscribePage())
            ->setTitle('Any')
            ->setActive(false);

        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $this->manager->setActive($page, true);
        $this->assertTrue($page->isActive());
    }

    public function testDeletePageCallsRepositoryRemove(): void
    {
        $page = new SubscribePage();

        $this->pageRepository
            ->expects($this->once())
            ->method('remove')
            ->with($page);

        $this->manager->deletePage($page);
    }

    public function testSyncPageDataWithEmptyExistingDataCreatesNewEntries(): void
    {
        $this->pageDataRepository
            ->expects($this->once())
            ->method('getByPage')
            ->with($this->page)
            ->willReturn([]);

        $this->pageDataRepository
            ->expects($this->exactly(2))
            ->method('persist')
            ->with($this->isInstanceOf(SubscribePageData::class));

        $this->entityManager
            ->expects($this->never())
            ->method('remove');

        $data = [
            'field1' => 'value1',
            'field2' => 'value2',
        ];

        $this->manager->syncPageData($data, $this->page);
    }

    public function testSyncPageDataCallsCopyToConfigWhenFeatureIsEnabled(): void
    {
        $data = ['subscribemessage' => 'updated'];

        $this->pageDataRepository
            ->expects($this->once())
            ->method('getByPage')
            ->with($this->page)
            ->willReturn([]);

        $this->configMigrationService
            ->expects($this->once())
            ->method('copyToConfig')
            ->with(page: $this->page, data: $data);

        $this->manager->syncPageData($data, $this->page);
    }

    public function testSyncPageDataSkipsCopyToConfigWhenFeatureIsDisabled(): void
    {
        $manager = $this->createManager(false);
        $data = ['subscribemessage' => 'updated'];

        $this->pageDataRepository
            ->expects($this->once())
            ->method('getByPage')
            ->with($this->page)
            ->willReturn([]);

        $this->configMigrationService
            ->expects($this->never())
            ->method('copyToConfig');

        $manager->syncPageData($data, $this->page);
    }

    public function testSyncPageDataUpdatesExistingEntries(): void
    {
        $pageData1 = new SubscribePageData();
        $pageData1->setName('field1')->setData('old_value1');
        $pageData2 = new SubscribePageData();
        $pageData2->setName('field2')->setData('old_value2');

        $this->pageDataRepository
            ->expects($this->once())
            ->method('getByPage')
            ->with($this->page)
            ->willReturn([$pageData1, $pageData2]);

        $this->pageDataRepository
            ->expects($this->never())
            ->method('persist');

        $this->entityManager
            ->expects($this->never())
            ->method('remove');

        $data = [
            'field1' => 'new_value1',
            'field2' => 'new_value2',
        ];

        $this->manager->syncPageData($data, $this->page);

        $this->assertSame('new_value1', $pageData1->getData());
        $this->assertSame('new_value2', $pageData2->getData());
    }

    public function testSyncPageDataRemovesExistingEntriesNotInNewData(): void
    {
        $pageData1 = new SubscribePageData();
        $pageData1->setName('field1')->setData('value1');
        $pageData2 = new SubscribePageData();
        $pageData2->setName('field2')->setData('value2');

        $this->pageDataRepository
            ->expects($this->once())
            ->method('getByPage')
            ->with($this->page)
            ->willReturn([$pageData1, $pageData2]);

        $this->pageDataRepository
            ->expects($this->never())
            ->method('persist');

        $this->entityManager
            ->expects($this->exactly(1))
            ->method('remove')
            ->with($pageData2);

        $data = [
            'field1' => 'value1',
        ];

        $this->manager->syncPageData($data, $this->page);
    }

    public function testSyncPageDataWithMixedOperationsCreateUpdateDelete(): void
    {
        $existingData1 = new SubscribePageData();
        $existingData1->setName('keep_and_update')->setData('old_value');
        $existingData2 = new SubscribePageData();
        $existingData2->setName('to_delete')->setData('delete_me');

        $this->pageDataRepository
            ->expects($this->once())
            ->method('getByPage')
            ->with($this->page)
            ->willReturn([$existingData1, $existingData2]);

        $this->pageDataRepository
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(SubscribePageData::class));

        $this->entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($existingData2);

        $data = [
            'keep_and_update' => 'updated_value',
            'new_field' => 'new_value',
        ];

        $this->manager->syncPageData($data, $this->page);

        $this->assertSame('updated_value', $existingData1->getData());
    }

    public function testSyncPageDataWithEmptyDataRemovesAllExistingEntries(): void
    {
        $page = $this->createMock(SubscribePage::class);
        $page->method('getId')->willReturn(11);

        $pageData1 = new SubscribePageData();
        $pageData1->setName('field1')->setData('value1');

        $pageData2 = new SubscribePageData();
        $pageData2->setName('field2')->setData('value2');

        $this->pageDataRepository
            ->expects($this->once())
            ->method('getByPage')
            ->with($page)
            ->willReturn([$pageData1, $pageData2]);

        $this->pageDataRepository
            ->expects($this->never())
            ->method('persist');

        $removedEntries = [];

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('remove')
            ->willReturnCallback(
                function (SubscribePageData $pageData) use (&$removedEntries): void {
                    $removedEntries[] = $pageData;
                }
            );

        $data = [];

        $this->manager->syncPageData($data, $page);

        $this->assertSame(
            [$pageData1, $pageData2],
            $removedEntries,
        );
    }

    public function testSyncPageDataPreservesExistingDataObjectsWhenKeepingEntries(): void
    {
        $originalPageData = new SubscribePageData();
        $originalPageData->setId(99)->setName('color')->setData('red');

        $this->pageDataRepository
            ->expects($this->once())
            ->method('getByPage')
            ->with($this->page)
            ->willReturn([$originalPageData]);

        $this->pageDataRepository
            ->expects($this->never())
            ->method('persist');

        $this->entityManager
            ->expects($this->never())
            ->method('remove');

        $data = [
            'color' => 'blue',
        ];

        $this->manager->syncPageData($data, $this->page);

        // Should have updated the same object, not created a new one
        $this->assertSame(99, $originalPageData->getId());
        $this->assertSame('color', $originalPageData->getName());
        $this->assertSame('blue', $originalPageData->getData());
    }
}
