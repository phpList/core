<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Handler;

use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Service\Handler\DecreaseCountConfirmUserAndDeleteBounceHandler;
use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DecreaseCountConfirmUserAndDeleteBounceHandlerTest extends TestCase
{
    private SubscriberHistoryManager&MockObject $historyManager;
    private SubscriberManager&MockObject $subscriberManager;
    private BounceManager&MockObject $bounceManager;
    private SubscriberRepository&MockObject $subscriberRepository;
    private DecreaseCountConfirmUserAndDeleteBounceHandler $handler;

    protected function setUp(): void
    {
        $this->historyManager = $this->createMock(SubscriberHistoryManager::class);
        $this->subscriberManager = $this->createMock(SubscriberManager::class);
        $this->bounceManager = $this->createMock(BounceManager::class);
        $this->subscriberRepository = $this->createMock(SubscriberRepository::class);
        $this->handler = new DecreaseCountConfirmUserAndDeleteBounceHandler(
            subscriberHistoryManager: $this->historyManager,
            subscriberManager: $this->subscriberManager,
            bounceManager: $this->bounceManager,
            subscriberRepository: $this->subscriberRepository,
        );
    }

    public function testSupportsOnlyDecreaseCountConfirmUserAndDeleteBounce(): void
    {
        $this->assertTrue($this->handler->supports('decreasecountconfirmuseranddeletebounce'));
        $this->assertFalse($this->handler->supports('deleteuser'));
        $this->assertFalse($this->handler->supports(''));
    }

    public function testHandleDecrementsMarksConfirmedAddsHistoryAndDeletesWhenNotConfirmed(): void
    {
        $subscriber = $this->createMock(Subscriber::class);
        $bounce = $this->createMock(Bounce::class);

        $this->subscriberManager->expects($this->once())->method('decrementBounceCount')->with($subscriber);
        $this->subscriberRepository->expects($this->once())->method('markConfirmed')->with(11);
        $this->historyManager->expects($this->once())->method('addHistory')->with(
            $subscriber,
            'Auto confirmed',
            $this->stringContains('bounce rule 77')
        );
        $this->bounceManager->expects($this->once())->method('delete')->with($bounce);

        $this->handler->handle([
            'subscriber' => $subscriber,
            'userId' => 11,
            'confirmed' => false,
            'ruleId' => 77,
            'bounce' => $bounce,
        ]);
    }

    public function testHandleOnlyDecrementsAndDeletesWhenAlreadyConfirmed(): void
    {
        $subscriber = $this->createMock(Subscriber::class);
        $bounce = $this->createMock(Bounce::class);

        $this->subscriberManager->expects($this->once())->method('decrementBounceCount')->with($subscriber);
        $this->subscriberRepository->expects($this->never())->method('markConfirmed');
        $this->historyManager->expects($this->never())->method('addHistory');
        $this->bounceManager->expects($this->once())->method('delete')->with($bounce);

        $this->handler->handle([
            'subscriber' => $subscriber,
            'userId' => 11,
            'confirmed' => true,
            'ruleId' => 77,
            'bounce' => $bounce,
        ]);
    }

    public function testHandleDeletesBounceEvenWithoutSubscriber(): void
    {
        $bounce = $this->createMock(Bounce::class);

        $this->subscriberManager->expects($this->never())->method('decrementBounceCount');
        $this->subscriberRepository->expects($this->never())->method('markConfirmed');
        $this->historyManager->expects($this->never())->method('addHistory');
        $this->bounceManager->expects($this->once())->method('delete')->with($bounce);

        $this->handler->handle([
            'confirmed' => true,
            'ruleId' => 1,
            'bounce' => $bounce,
        ]);
    }
}
