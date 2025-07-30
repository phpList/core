<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service\Provider;

use Doctrine\Common\Collections\ArrayCollection;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberList;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Provider\SubscriberProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SubscriberProviderTest extends TestCase
{
    private SubscriberRepository|MockObject $subscriberRepository;
    private SubscriberProvider $subscriberProvider;

    protected function setUp(): void
    {
        $this->subscriberRepository = $this->createMock(SubscriberRepository::class);

        $this->subscriberProvider = new SubscriberProvider($this->subscriberRepository);
    }

    public function testGetSubscribersForMessageWithNoListsReturnsEmptyArray(): void
    {
        $message = $this->createMock(Message::class);
        $message->method('getId')->willReturn(123);
        $message->method('getSubscriberLists')->willReturn(new ArrayCollection());

        $this->subscriberRepository
            ->expects($this->never())
            ->method('getSubscribersBySubscribedListId');

        $result = $this->subscriberProvider->getSubscribersForMessage($message);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetSubscribersForMessageWithOneListButNoSubscribersReturnsEmptyArray(): void
    {
        $message = $this->createMock(Message::class);
        $message->method('getId')->willReturn(123);

        $subscriberList = $this->createMock(SubscriberList::class);
        $subscriberList->method('getId')->willReturn(456);
        $message->method('getSubscriberLists')->willReturn(new ArrayCollection([$subscriberList]));

        $this->subscriberRepository
            ->expects($this->once())
            ->method('getSubscribersBySubscribedListId')
            ->with(456)
            ->willReturn([]);

        $result = $this->subscriberProvider->getSubscribersForMessage($message);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetSubscribersForMessageWithOneListAndSubscribersReturnsSubscribers(): void
    {
        $message = $this->createMock(Message::class);
        $message->method('getId')->willReturn(123);

        $subscriberList = $this->createMock(SubscriberList::class);
        $subscriberList->method('getId')->willReturn(456);
        $message->method('getSubscriberLists')->willReturn(new ArrayCollection([$subscriberList]));

        $subscriber1 = $this->createMock(Subscriber::class);
        $subscriber1->method('getId')->willReturn(1);
        $subscriber2 = $this->createMock(Subscriber::class);
        $subscriber2->method('getId')->willReturn(2);

        $this->subscriberRepository
            ->expects($this->once())
            ->method('getSubscribersBySubscribedListId')
            ->with(456)
            ->willReturn([$subscriber1, $subscriber2]);

        $result = $this->subscriberProvider->getSubscribersForMessage($message);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame($subscriber1, $result[0]);
        $this->assertSame($subscriber2, $result[1]);
    }

    public function testGetSubscribersForMessageWithMultipleListsReturnsUniqueSubscribers(): void
    {
        $message = $this->createMock(Message::class);
        $message->method('getId')->willReturn(123);

        $subscriberList1 = $this->createMock(SubscriberList::class);
        $subscriberList1->method('getId')->willReturn(456);
        $subscriberList2 = $this->createMock(SubscriberList::class);
        $subscriberList2->method('getId')->willReturn(789);
        $message->method('getSubscriberLists')->willReturn(new ArrayCollection([$subscriberList1, $subscriberList2]));

        $subscriber1 = $this->createMock(Subscriber::class);
        $subscriber1->method('getId')->willReturn(1);
        $subscriber2 = $this->createMock(Subscriber::class);
        $subscriber2->method('getId')->willReturn(2);
        $subscriber3 = $this->createMock(Subscriber::class);
        $subscriber3->method('getId')->willReturn(3);

        $this->subscriberRepository
            ->expects($this->exactly(2))
            ->method('getSubscribersBySubscribedListId')
            ->willReturnMap([
                [456, [$subscriber1, $subscriber2]],
                [789, [$subscriber2, $subscriber3]],
            ]);

        $result = $this->subscriberProvider->getSubscribersForMessage($message);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);

        $this->assertContains($subscriber1, $result);
        $this->assertContains($subscriber2, $result);
        $this->assertContains($subscriber3, $result);
    }
}
