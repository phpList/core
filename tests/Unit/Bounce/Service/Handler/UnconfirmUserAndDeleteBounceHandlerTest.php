<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Bounce\Service\Handler;

use PhpList\Core\Bounce\Service\Handler\UnconfirmUserAndDeleteBounceHandler;
use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Translator;

class UnconfirmUserAndDeleteBounceHandlerTest extends TestCase
{
    private SubscriberHistoryManager&MockObject $historyManager;
    private SubscriberRepository&MockObject $subscriberRepository;
    private BounceManager&MockObject $bounceManager;
    private UnconfirmUserAndDeleteBounceHandler $handler;

    protected function setUp(): void
    {
        $this->historyManager = $this->createMock(SubscriberHistoryManager::class);
        $this->subscriberRepository = $this->createMock(SubscriberRepository::class);
        $this->bounceManager = $this->createMock(BounceManager::class);
        $this->handler = new UnconfirmUserAndDeleteBounceHandler(
            subscriberHistoryManager: $this->historyManager,
            subscriberRepository: $this->subscriberRepository,
            bounceManager: $this->bounceManager,
            translator: new Translator('en')
        );
    }

    public function testSupportsOnlyUnconfirmUserAndDeleteBounce(): void
    {
        $this->assertTrue($this->handler->supports('unconfirmuseranddeletebounce'));
        $this->assertFalse($this->handler->supports('unconfirmuser'));
        $this->assertFalse($this->handler->supports(''));
    }

    public function testHandleUnconfirmsAndAddsHistoryAndDeletesBounce(): void
    {
        $subscriber = $this->createMock(Subscriber::class);
        $bounce = $this->createMock(Bounce::class);

        $this->subscriberRepository->expects($this->once())->method('markUnconfirmed')->with(10);
        $this->historyManager->expects($this->once())->method('addHistory')->with(
            $subscriber,
            'Auto unconfirmed',
            $this->stringContains('bounce rule 3')
        );
        $this->bounceManager->expects($this->once())->method('delete')->with($bounce);

        $this->handler->handle([
            'subscriber' => $subscriber,
            'userId' => 10,
            'confirmed' => true,
            'ruleId' => 3,
            'bounce' => $bounce,
        ]);
    }

    public function testHandleDeletesBounceAndSkipsUnconfirmWhenNotConfirmedOrNoSubscriber(): void
    {
        $subscriber = $this->createMock(Subscriber::class);
        $bounce = $this->createMock(Bounce::class);

        $this->subscriberRepository->expects($this->never())->method('markUnconfirmed');
        $this->historyManager->expects($this->never())->method('addHistory');
        $this->bounceManager->expects($this->exactly(2))->method('delete')->with($bounce);

        // Not confirmed
        $this->handler->handle([
            'subscriber' => $subscriber,
            'userId' => 10,
            'confirmed' => false,
            'ruleId' => 3,
            'bounce' => $bounce,
        ]);

        // No subscriber
        $this->handler->handle([
            'userId' => 10,
            'confirmed' => true,
            'ruleId' => 3,
            'bounce' => $bounce,
        ]);
    }
}
