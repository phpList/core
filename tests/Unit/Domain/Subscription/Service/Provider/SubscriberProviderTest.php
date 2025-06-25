<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service\Provider;

use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Repository\ListMessageRepository;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Provider\SubscriberProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SubscriberProviderTest extends TestCase
{
    private ListMessageRepository|MockObject $listMessageRepository;
    private SubscriberRepository|MockObject $subscriberRepository;
    private SubscriberProvider $subscriberProvider;

    protected function setUp(): void
    {
        $this->listMessageRepository = $this->createMock(ListMessageRepository::class);
        $this->subscriberRepository = $this->createMock(SubscriberRepository::class);

        $this->subscriberProvider = new SubscriberProvider(
            $this->listMessageRepository,
            $this->subscriberRepository
        );
    }

    public function testGetSubscribersForMessageWithNoListsReturnsEmptyArray(): void
    {
        $message = $this->createMock(Message::class);
        $message->method('getId')->willReturn(123);

        $this->listMessageRepository
            ->expects($this->once())
            ->method('getListIdsByMessageId')
            ->with(123)
            ->willReturn([]);

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

        $this->listMessageRepository
            ->expects($this->once())
            ->method('getListIdsByMessageId')
            ->with(123)
            ->willReturn([456]);

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

        $subscriber1 = $this->createMock(Subscriber::class);
        $subscriber1->method('getId')->willReturn(1);
        $subscriber2 = $this->createMock(Subscriber::class);
        $subscriber2->method('getId')->willReturn(2);

        $this->listMessageRepository
            ->expects($this->once())
            ->method('getListIdsByMessageId')
            ->with(123)
            ->willReturn([456]);

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

        $subscriber1 = $this->createMock(Subscriber::class);
        $subscriber1->method('getId')->willReturn(1);
        $subscriber2 = $this->createMock(Subscriber::class);
        $subscriber2->method('getId')->willReturn(2);
        $subscriber3 = $this->createMock(Subscriber::class);
        $subscriber3->method('getId')->willReturn(3);

        $this->listMessageRepository
            ->expects($this->once())
            ->method('getListIdsByMessageId')
            ->with(123)
            ->willReturn([456, 789]);

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
