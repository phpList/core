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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
            ->method('save')
            ->with($this->isInstanceOf(SubscribePage::class));

        $page = $this->manager->createPage('My Page', true, $owner);

        $this->assertInstanceOf(SubscribePage::class, $page);
        $this->assertSame('My Page', $page->getTitle());
        $this->assertTrue($page->isActive());
        $this->assertSame($owner, $page->getOwner());
    }

    public function testGetPageReturnsPage(): void
    {
        $page = new SubscribePage();
        $this->pageRepository
            ->expects($this->once())
            ->method('find')
            ->with(123)
            ->willReturn($page);

        $result = $this->manager->getPage(123);

        $this->assertSame($page, $result);
    }

    public function testGetPageThrowsWhenNotFound(): void
    {
        $this->pageRepository
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Subscribe page not found');

        $this->manager->getPage(999);
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
            ->expects($this->once())
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
            ->expects($this->once())
            ->method('flush');

        $updated = $this->manager->updatePage(page: $page, title: null, active: null, owner: null);

        $this->assertSame('Keep Title', $updated->getTitle());
        $this->assertTrue($updated->isActive());
        $this->assertSame($owner, $updated->getOwner());
    }

    public function testSetActiveSetsFlagAndFlushes(): void
    {
        $page = (new SubscribePage())
            ->setTitle('Any')
            ->setActive(false);

        $this->entityManager
            ->expects($this->once())
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
            ->method('findByPageAndName')
            ->with($page, 'key')
            ->willReturn($data);

        $result = $this->manager->getPageData($page, 'key');
        $this->assertSame('value', $result);
    }

    public function testGetPageDataReturnsNullWhenNotFound(): void
    {
        $page = new SubscribePage();

        $this->pageDataRepository
            ->expects($this->once())
            ->method('findByPageAndName')
            ->with($page, 'missing')
            ->willReturn(null);

        $result = $this->manager->getPageData($page, 'missing');
        $this->assertNull($result);
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

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

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

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->manager->setPageData($page, 'greeting', 'hello');

        $this->assertInstanceOf(SubscribePageData::class, $result);
        $this->assertSame(123, $result->getId());
        $this->assertSame('greeting', $result->getName());
        $this->assertSame('hello', $result->getData());
    }
}
