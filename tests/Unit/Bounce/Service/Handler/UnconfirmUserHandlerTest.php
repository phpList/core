<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Bounce\Service\Handler;

use PhpList\Core\Bounce\Service\Handler\UnconfirmUserHandler;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Translator;

class UnconfirmUserHandlerTest extends TestCase
{
    private SubscriberRepository&MockObject $subscriberRepository;
    private SubscriberHistoryManager&MockObject $historyManager;
    private UnconfirmUserHandler $handler;

    protected function setUp(): void
    {
        $this->subscriberRepository = $this->createMock(SubscriberRepository::class);
        $this->historyManager = $this->createMock(SubscriberHistoryManager::class);
        $this->handler = new UnconfirmUserHandler(
            subscriberRepository: $this->subscriberRepository,
            subscriberHistoryManager: $this->historyManager,
            translator: new Translator('en')
        );
    }

    public function testSupportsOnlyUnconfirmUser(): void
    {
        $this->assertTrue($this->handler->supports('unconfirmuser'));
        $this->assertFalse($this->handler->supports('blacklistuser'));
        $this->assertFalse($this->handler->supports(''));
    }

    public function testHandleMarksUnconfirmedAndAddsHistoryWhenSubscriberPresentAndConfirmed(): void
    {
        $subscriber = $this->createMock(Subscriber::class);

        $this->subscriberRepository->expects($this->once())->method('markUnconfirmed')->with(123);
        $this->historyManager->expects($this->once())->method('addHistory')->with(
            $subscriber,
            'Auto unconfirmed',
            $this->stringContains('bounce rule 9')
        );

        $this->handler->handle([
            'subscriber' => $subscriber,
            'userId' => 123,
            'confirmed' => true,
            'ruleId' => 9,
        ]);
    }

    public function testHandleDoesNothingWhenNotConfirmedOrNoSubscriber(): void
    {
        $subscriber = $this->createMock(Subscriber::class);
        $this->subscriberRepository->expects($this->never())->method('markUnconfirmed');
        $this->historyManager->expects($this->never())->method('addHistory');

        // Not confirmed
        $this->handler->handle([
            'subscriber' => $subscriber,
            'userId' => 44,
            'confirmed' => false,
            'ruleId' => 1,
        ]);

        // No subscriber
        $this->handler->handle([
            'userId' => 44,
            'confirmed' => true,
            'ruleId' => 1,
        ]);
    }
}
