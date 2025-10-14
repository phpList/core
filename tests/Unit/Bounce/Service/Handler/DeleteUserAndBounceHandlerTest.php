<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Bounce\Service\Handler;

use PhpList\Core\Bounce\Service\Handler\DeleteUserAndBounceHandler;
use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DeleteUserAndBounceHandlerTest extends TestCase
{
    private BounceManager&MockObject $bounceManager;
    private SubscriberManager&MockObject $subscriberManager;
    private DeleteUserAndBounceHandler $handler;

    protected function setUp(): void
    {
        $this->bounceManager = $this->createMock(BounceManager::class);
        $this->subscriberManager = $this->createMock(SubscriberManager::class);
        $this->handler = new DeleteUserAndBounceHandler(
            bounceManager: $this->bounceManager,
            subscriberManager: $this->subscriberManager
        );
    }

    public function testSupportsOnlyDeleteUserAndBounce(): void
    {
        $this->assertTrue($this->handler->supports('deleteuserandbounce'));
        $this->assertFalse($this->handler->supports('deleteuser'));
        $this->assertFalse($this->handler->supports(''));
    }

    public function testHandleDeletesUserWhenPresentAndAlwaysDeletesBounce(): void
    {
        $subscriber = $this->createMock(Subscriber::class);
        $bounce = $this->createMock(Bounce::class);

        $this->subscriberManager->expects($this->once())->method('deleteSubscriber')->with($subscriber);
        $this->bounceManager->expects($this->once())->method('delete')->with($bounce);

        $this->handler->handle([
            'subscriber' => $subscriber,
            'bounce' => $bounce,
        ]);
    }

    public function testHandleSkipsUserDeletionWhenNoSubscriberButDeletesBounce(): void
    {
        $bounce = $this->createMock(Bounce::class);

        $this->subscriberManager->expects($this->never())->method('deleteSubscriber');
        $this->bounceManager->expects($this->once())->method('delete')->with($bounce);

        $this->handler->handle([
            'bounce' => $bounce,
        ]);
    }
}
