<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Handler;

use PhpList\Core\Domain\Messaging\Service\Handler\BlacklistEmailHandler;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\SubscriberBlacklistService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Translator;

class BlacklistEmailHandlerTest extends TestCase
{
    private SubscriberHistoryManager&MockObject $historyManager;
    private SubscriberBlacklistService&MockObject $blacklistService;
    private BlacklistEmailHandler $handler;

    protected function setUp(): void
    {
        $this->historyManager = $this->createMock(SubscriberHistoryManager::class);
        $this->blacklistService = $this->createMock(SubscriberBlacklistService::class);
        $this->handler = new BlacklistEmailHandler(
            subscriberHistoryManager: $this->historyManager,
            blacklistService: $this->blacklistService,
            translator: new Translator('en'),
        );
    }

    public function testSupportsOnlyBlacklistEmail(): void
    {
        $this->assertTrue($this->handler->supports('blacklistemail'));
        $this->assertFalse($this->handler->supports('blacklistuser'));
        $this->assertFalse($this->handler->supports(''));
    }

    public function testHandleBlacklistsAndAddsHistoryWhenSubscriberPresent(): void
    {
        $subscriber = $this->createMock(Subscriber::class);

        $this->blacklistService
            ->expects($this->once())
            ->method('blacklist')
            ->with(
                $subscriber,
                $this->stringContains('Email address auto blacklisted by bounce rule 42')
            );

        $this->historyManager
            ->expects($this->once())
            ->method('addHistory')
            ->with(
                $subscriber,
                'Auto Unsubscribed',
                $this->stringContains('email auto unsubscribed for bounce rule 42')
            );

        $this->handler->handle([
            'subscriber' => $subscriber,
            'ruleId' => 42,
        ]);
    }

    public function testHandleDoesNothingWhenNoSubscriber(): void
    {
        $this->blacklistService->expects($this->never())->method('blacklist');
        $this->historyManager->expects($this->never())->method('addHistory');

        $this->handler->handle([
            'ruleId' => 1,
        ]);
    }
}
