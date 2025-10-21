<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Bounce\Service\Handler;

use PhpList\Core\Bounce\Service\Handler\BlacklistUserAndDeleteBounceHandler;
use PhpList\Core\Bounce\Service\SubscriberBlacklistService;
use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Translator;

class BlacklistUserAndDeleteBounceHandlerTest extends TestCase
{
    private SubscriberHistoryManager&MockObject $historyManager;
    private BounceManager&MockObject $bounceManager;
    private SubscriberBlacklistService&MockObject $blacklistService;
    private BlacklistUserAndDeleteBounceHandler $handler;

    protected function setUp(): void
    {
        $this->historyManager = $this->createMock(SubscriberHistoryManager::class);
        $this->bounceManager = $this->createMock(BounceManager::class);
        $this->blacklistService = $this->createMock(SubscriberBlacklistService::class);
        $this->handler = new BlacklistUserAndDeleteBounceHandler(
            subscriberHistoryManager: $this->historyManager,
            bounceManager: $this->bounceManager,
            blacklistService: $this->blacklistService,
            translator: new Translator('en')
        );
    }

    public function testSupportsOnlyBlacklistUserAndDeleteBounce(): void
    {
        $this->assertTrue($this->handler->supports('blacklistuseranddeletebounce'));
        $this->assertFalse($this->handler->supports('blacklistuser'));
        $this->assertFalse($this->handler->supports(''));
    }

    public function testHandleBlacklistsAddsHistoryAndDeletesBounceWhenSubscriberPresentAndNotBlacklisted(): void
    {
        $subscriber = $this->createMock(Subscriber::class);
        $bounce = $this->createMock(Bounce::class);

        $this->blacklistService->expects($this->once())->method('blacklist')->with(
            $subscriber,
            $this->stringContains('Subscriber auto blacklisted by bounce rule 13')
        );
        $this->historyManager->expects($this->once())->method('addHistory')->with(
            $subscriber,
            'Auto Unsubscribed',
            $this->stringContains('User auto unsubscribed for bounce rule 13')
        );
        $this->bounceManager->expects($this->once())->method('delete')->with($bounce);

        $this->handler->handle([
            'subscriber' => $subscriber,
            'blacklisted' => false,
            'ruleId' => 13,
            'bounce' => $bounce,
        ]);
    }

    public function testHandleSkipsBlacklistAndHistoryWhenNoSubscriberOrAlreadyBlacklistedButDeletesBounce(): void
    {
        $subscriber = $this->createMock(Subscriber::class);
        $bounce = $this->createMock(Bounce::class);

        $this->blacklistService->expects($this->never())->method('blacklist');
        $this->historyManager->expects($this->never())->method('addHistory');
        $this->bounceManager->expects($this->exactly(2))->method('delete')->with($bounce);

        // Already blacklisted
        $this->handler->handle([
            'subscriber' => $subscriber,
            'blacklisted' => true,
            'ruleId' => 13,
            'bounce' => $bounce,
        ]);

        // No subscriber
        $this->handler->handle([
            'blacklisted' => false,
            'ruleId' => 13,
            'bounce' => $bounce,
        ]);
    }
}
