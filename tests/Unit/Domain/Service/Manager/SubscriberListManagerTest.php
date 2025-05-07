<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Service\Manager;

use PhpList\Core\Domain\Model\Identity\Administrator;
use PhpList\Core\Domain\Model\Subscription\Dto\CreateSubscriberListDto;
use PhpList\Core\Domain\Model\Subscription\SubscriberList;
use PhpList\Core\Domain\Repository\Subscription\SubscriberListRepository;
use PhpList\Core\Domain\Service\Manager\SubscriberListManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SubscriberListManagerTest extends TestCase
{
    private SubscriberListRepository&MockObject $subscriberListRepository;
    private SubscriberListManager $manager;

    protected function setUp(): void
    {
        $this->subscriberListRepository = $this->createMock(SubscriberListRepository::class);
        $this->manager = new SubscriberListManager($this->subscriberListRepository);
    }

    public function testCreateSubscriberList(): void
    {
        $request = new CreateSubscriberListDto(
            name: 'New List',
            isPublic: true,
            listPosition: 3,
            description: 'Description'
        );

        $admin = new Administrator();

        $this->subscriberListRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(SubscriberList::class));

        $result = $this->manager->createSubscriberList($request, $admin);

        $this->assertSame('New List', $result->getName());
        $this->assertSame('Description', $result->getDescription());
        $this->assertSame(3, $result->getListPosition());
        $this->assertTrue($result->isPublic());
        $this->assertSame($admin, $result->getOwner());
    }

    public function testGetPaginated(): void
    {
        $list = new SubscriberList();
        $this->subscriberListRepository
            ->expects($this->once())
            ->method('getAfterId')
            ->willReturn([$list]);

        $result = $this->manager->getPaginated(0, 1);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame($list, $result[0]);
    }

    public function testDeleteSubscriberList(): void
    {
        $subscriberList = new SubscriberList();

        $this->subscriberListRepository
            ->expects($this->once())
            ->method('remove')
            ->with($subscriberList);

        $this->manager->delete($subscriberList);
    }
}
