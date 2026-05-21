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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SubscribePageManagerTest extends TestCase
{
    private SubscriberPageRepository|MockObject $pageRepository;
    private SubscriberPageDataRepository|MockObject $pageDataRepository;
    private EntityManagerInterface|MockObject $entityManager;
    private SubscribePageManager $manager;

    protected function setUp(): void
    {
        $this->pageRepository = $this->createMock(SubscriberPageRepository::class);
        $this->pageDataRepository = $this->createMock(SubscriberPageDataRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->manager = new SubscribePageManager(
            pageRepository: $this->pageRepository,
            pageDataRepository: $this->pageDataRepository,
            entityManager: $this->entityManager,
        );
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

    public function testGetPageDataReturnsStringWhenFound(): void
    {
        $page = new SubscribePage();
        $data = $this->createMock(SubscribePageData::class);
        $data->expects($this->once())->method('getData')->willReturn('value');

        $this->pageDataRepository
            ->expects($this->once())
            ->method('getByPage')
            ->with($page)
            ->willReturn([$data]);

        $result = $this->manager->getPageData($page);
        $this->assertIsArray($result);
        $this->assertSame('value', $result[0]->getData());
    }

    public function testGetPageDataReturnsNullWhenNotFound(): void
    {
        $page = new SubscribePage();

        $this->pageDataRepository
            ->expects($this->once())
            ->method('getByPage')
            ->with($page)
            ->willReturn([]);

        $result = $this->manager->getPageData($page);
        $this->assertEmpty($result);
    }

    public function testSetPageDataUpdatesExistingDataAndFlushes(): void
    {
        $page = new SubscribePage();
        $existing = new SubscribePageData();
        $existing->setId(5)->setName('color')->setData('red');

        $this->pageDataRepository
            ->expects($this->once())
            ->method('findByPageAndName')
            ->with($page, 'color')
            ->willReturn($existing);

        $this->entityManager
            ->expects($this->never())
            ->method('persist');

        $result = $this->manager->setPageData($page, 'color', 'blue');

        $this->assertSame($existing, $result);
        $this->assertSame('blue', $result->getData());
    }

    public function testSetPageDataCreatesNewWhenMissingAndPersistsAndFlushes(): void
    {
        $page = $this->getMockBuilder(SubscribePage::class)
            ->onlyMethods(['getId'])
            ->getMock();
        $page->method('getId')->willReturn(123);

        $this->pageDataRepository
            ->expects($this->once())
            ->method('findByPageAndName')
            ->with($page, 'greeting')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(SubscribePageData::class));

        $result = $this->manager->setPageData($page, 'greeting', 'hello');

        $this->assertInstanceOf(SubscribePageData::class, $result);
        $this->assertSame(123, $result->getId());
        $this->assertSame('greeting', $result->getName());
        $this->assertSame('hello', $result->getData());
    }

    public function testSyncPageDataWithEmptyExistingDataCreatesNewEntries(): void
    {
        $page = $this->getMockBuilder(SubscribePage::class)
            ->onlyMethods(['getId'])
            ->getMock();
        $page->method('getId')->willReturn(42);

        $this->pageDataRepository
            ->expects($this->once())
            ->method('getByPage')
            ->with($page)
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

        $this->manager->syncPageData($data, $page);
    }

    public function testSyncPageDataUpdatesExistingEntries(): void
    {
        $page = new SubscribePage();
        $pageData1 = new SubscribePageData();
        $pageData1->setName('field1')->setData('old_value1');
        $pageData2 = new SubscribePageData();
        $pageData2->setName('field2')->setData('old_value2');

        $this->pageDataRepository
            ->expects($this->once())
            ->method('getByPage')
            ->with($page)
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

        $this->manager->syncPageData($data, $page);

        $this->assertSame('new_value1', $pageData1->getData());
        $this->assertSame('new_value2', $pageData2->getData());
    }

    public function testSyncPageDataRemovesExistingEntriesNotInNewData(): void
    {
        $page = new SubscribePage();
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

        $this->entityManager
            ->expects($this->exactly(1))
            ->method('remove')
            ->with($pageData2);

        $data = [
            'field1' => 'value1',
        ];

        $this->manager->syncPageData($data, $page);
    }

    public function testSyncPageDataWithMixedOperationsCreateUpdateDelete(): void
    {
        $page = $this->getMockBuilder(SubscribePage::class)
            ->onlyMethods(['getId'])
            ->getMock();
        $page->method('getId')->willReturn(10);

        $existingData1 = new SubscribePageData();
        $existingData1->setName('keep_and_update')->setData('old_value');
        $existingData2 = new SubscribePageData();
        $existingData2->setName('to_delete')->setData('delete_me');

        $this->pageDataRepository
            ->expects($this->once())
            ->method('getByPage')
            ->with($page)
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

        $this->manager->syncPageData($data, $page);

        $this->assertSame('updated_value', $existingData1->getData());
    }

    public function testSyncPageDataWithEmptyDataRemovesAllExistingEntries(): void
    {
        $page = new SubscribePage();
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

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('remove')
            ->withConsecutive([$pageData1], [$pageData2]);

        $data = [];

        $this->manager->syncPageData($data, $page);
    }

    public function testSyncPageDataPreservesExistingDataObjectsWhenKeepingEntries(): void
    {
        $page = new SubscribePage();
        $originalPageData = new SubscribePageData();
        $originalPageData->setId(99)->setName('color')->setData('red');

        $this->pageDataRepository
            ->expects($this->once())
            ->method('getByPage')
            ->with($page)
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

        $this->manager->syncPageData($data, $page);

        // Should have updated the same object, not created a new one
        $this->assertSame(99, $originalPageData->getId());
        $this->assertSame('color', $originalPageData->getName());
        $this->assertSame('blue', $originalPageData->getData());
    }
}
