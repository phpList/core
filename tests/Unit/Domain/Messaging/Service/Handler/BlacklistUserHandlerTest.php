<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Handler;

use PhpList\Core\Domain\Messaging\Service\Handler\BlacklistUserHandler;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\SubscriberBlacklistService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Translator;

class BlacklistUserHandlerTest extends TestCase
{
    private SubscriberHistoryManager&MockObject $historyManager;
    private SubscriberBlacklistService&MockObject $blacklistService;
    private BlacklistUserHandler $handler;

    protected function setUp(): void
    {
        $this->historyManager = $this->createMock(SubscriberHistoryManager::class);
        $this->blacklistService = $this->createMock(SubscriberBlacklistService::class);
        $this->handler = new BlacklistUserHandler(
            subscriberHistoryManager: $this->historyManager,
            blacklistService: $this->blacklistService,
            translator: new Translator('en')
        );
    }

    public function testSupportsOnlyBlacklistUser(): void
    {
        $this->assertTrue($this->handler->supports('blacklistuser'));
        $this->assertFalse($this->handler->supports('unconfirmuser'));
        $this->assertFalse($this->handler->supports(''));
    }

    public function testHandleBlacklistsAndAddsHistoryWhenSubscriberPresentAndNotBlacklisted(): void
    {
        $subscriber = $this->createMock(Subscriber::class);

        $this->blacklistService
            ->expects($this->once())
            ->method('blacklist')
            ->with(
                $subscriber,
                $this->stringContains('bounce rule 17')
            );

        $this->historyManager
            ->expects($this->once())
            ->method('addHistory')
            ->with(
                $subscriber,
                'Auto Unsubscribed',
                $this->stringContains('bounce rule 17')
            );

        $this->handler->handle([
            'subscriber' => $subscriber,
            'blacklisted' => false,
            'ruleId' => 17,
        ]);
    }

    public function testHandleDoesNothingWhenAlreadyBlacklistedOrNoSubscriber(): void
    {
        $subscriber = $this->createMock(Subscriber::class);
        $this->blacklistService->expects($this->never())->method('blacklist');
        $this->historyManager->expects($this->never())->method('addHistory');

        // Already blacklisted
        $this->handler->handle([
            'subscriber' => $subscriber,
            'blacklisted' => true,
            'ruleId' => 5,
        ]);

        // No subscriber provided
        $this->handler->handle([
            'blacklisted' => false,
            'ruleId' => 5,
        ]);
    }
}
