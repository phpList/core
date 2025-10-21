<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Processor;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Bounce\Service\Manager\BounceManager;
use PhpList\Core\Bounce\Service\Processor\BounceDataProcessor;
use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BounceDataProcessorTest extends TestCase
{
    private BounceManager&MockObject $bounceManager;
    private SubscriberRepository&MockObject $subscriberRepository;
    private MessageRepository&MockObject $messageRepository;
    private LoggerInterface&MockObject $logger;
    private SubscriberManager&MockObject $subscriberManager;
    private SubscriberHistoryManager&MockObject $historyManager;
    private Bounce&MockObject $bounce;

    protected function setUp(): void
    {
        $this->bounceManager = $this->createMock(BounceManager::class);
        $this->subscriberRepository = $this->createMock(SubscriberRepository::class);
        $this->messageRepository = $this->createMock(MessageRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->subscriberManager = $this->createMock(SubscriberManager::class);
        $this->historyManager = $this->createMock(SubscriberHistoryManager::class);
        $this->bounce = $this->createMock(Bounce::class);
    }

    private function makeProcessor(): BounceDataProcessor
    {
        return new BounceDataProcessor(
            bounceManager: $this->bounceManager,
            subscriberRepository: $this->subscriberRepository,
            messageRepository: $this->messageRepository,
            logger: $this->logger,
            subscriberManager: $this->subscriberManager,
            subscriberHistoryManager: $this->historyManager,
            entityManager: $this->createMock(EntityManagerInterface::class),
        );
    }

    public function testSystemMessageWithUserAddsHistory(): void
    {
        $processor = $this->makeProcessor();
        $date = new DateTimeImmutable('2020-01-01');

        $this->bounce->method('getId')->willReturn(77);

        $this->bounceManager
            ->expects($this->once())
            ->method('update')
            ->with($this->bounce, 'bounced system message', '123 marked unconfirmed');
        $this->bounceManager
            ->expects($this->once())
            ->method('linkUserMessageBounce')
            ->with($this->bounce, $date, 123);
        $this->subscriberRepository->expects($this->once())->method('markUnconfirmed')->with(123);
        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('system message bounced, user marked unconfirmed', ['userId' => 123]);

        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->method('getId')->willReturn(123);
        $this->subscriberManager->method('getSubscriberById')->with(123)->willReturn($subscriber);
        $this->historyManager
            ->expects($this->once())
            ->method('addHistory')
            ->with($subscriber, 'Bounced system message', 'User marked unconfirmed. Bounce #77');

        $res = $processor->process($this->bounce, 'systemmessage', 123, $date);
        $this->assertTrue($res);
    }

    public function testSystemMessageUnknownUser(): void
    {
        $processor = $this->makeProcessor();
        $this->bounceManager
            ->expects($this->once())
            ->method('update')
            ->with($this->bounce, 'bounced system message', 'unknown user');
        $this->logger->expects($this->once())->method('info')->with('system message bounced, but unknown user');
        $res = $processor->process($this->bounce, 'systemmessage', null, new DateTimeImmutable());
        $this->assertTrue($res);
    }

    public function testKnownMessageAndUserNew(): void
    {
        $processor = $this->makeProcessor();
        $date = new DateTimeImmutable();
        $this->bounceManager->method('existsUserMessageBounce')->with(5, 10)->willReturn(false);
        $this->bounceManager
            ->expects($this->once())
            ->method('linkUserMessageBounce')
            ->with($this->bounce, $date, 5, 10);
        $this->bounceManager
            ->expects($this->once())
            ->method('update')
            ->with($this->bounce, 'bounced list message 10', '5 bouncecount increased');
        $this->messageRepository->expects($this->once())->method('incrementBounceCount')->with(10);
        $this->subscriberRepository->expects($this->once())->method('incrementBounceCount')->with(5);
        $res = $processor->process($this->bounce, '10', 5, $date);
        $this->assertTrue($res);
    }

    public function testKnownMessageAndUserDuplicate(): void
    {
        $processor = $this->makeProcessor();
        $date = new DateTimeImmutable();
        $this->bounceManager->method('existsUserMessageBounce')->with(5, 10)->willReturn(true);
        $this->bounceManager
            ->expects($this->once())
            ->method('linkUserMessageBounce')
            ->with($this->bounce, $date, 5, 10);
        $this->bounceManager
            ->expects($this->once())
            ->method('update')
            ->with($this->bounce, 'duplicate bounce for 5', 'duplicate bounce for subscriber 5 on message 10');
        $res = $processor->process($this->bounce, '10', 5, $date);
        $this->assertTrue($res);
    }

    public function testUserOnly(): void
    {
        $processor = $this->makeProcessor();
        $this->bounceManager
            ->expects($this->once())
            ->method('update')
            ->with($this->bounce, 'bounced unidentified message', '5 bouncecount increased');
        $this->subscriberRepository->expects($this->once())->method('incrementBounceCount')->with(5);
        $res = $processor->process($this->bounce, null, 5, new DateTimeImmutable());
        $this->assertTrue($res);
    }

    public function testMessageOnly(): void
    {
        $processor = $this->makeProcessor();
        $this->bounceManager
            ->expects($this->once())
            ->method('update')
            ->with($this->bounce, 'bounced list message 10', 'unknown user');
        $this->messageRepository->expects($this->once())->method('incrementBounceCount')->with(10);
        $res = $processor->process($this->bounce, '10', null, new DateTimeImmutable());
        $this->assertTrue($res);
    }

    public function testNeitherMessageNorUser(): void
    {
        $processor = $this->makeProcessor();
        $this->bounceManager
            ->expects($this->once())
            ->method('update')
            ->with($this->bounce, 'unidentified bounce', 'not processed');
        $res = $processor->process($this->bounce, null, null, new DateTimeImmutable());
        $this->assertFalse($res);
    }
}
