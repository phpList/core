<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Bounce\Service\Handler;

use PhpList\Core\Bounce\Service\Handler\DeleteUserHandler;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DeleteUserHandlerTest extends TestCase
{
    private SubscriberManager&MockObject $subscriberManager;
    private LoggerInterface&MockObject $logger;
    private DeleteUserHandler $handler;

    protected function setUp(): void
    {
        $this->subscriberManager = $this->createMock(SubscriberManager::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->handler = new DeleteUserHandler(subscriberManager: $this->subscriberManager, logger: $this->logger);
    }

    public function testSupportsOnlyDeleteUser(): void
    {
        $this->assertTrue($this->handler->supports('deleteuser'));
        $this->assertFalse($this->handler->supports('deleteuserandbounce'));
        $this->assertFalse($this->handler->supports(''));
    }

    public function testHandleLogsAndDeletesWhenSubscriberPresent(): void
    {
        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->method('getEmail')->willReturn('user@example.com');

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                'User deleted by bounce rule',
                $this->callback(function ($context) {
                    return isset($context['user'], $context['rule'])
                        && $context['user'] === 'user@example.com'
                        && $context['rule'] === 42;
                })
            );

        $this->subscriberManager
            ->expects($this->once())
            ->method('deleteSubscriber')
            ->with($subscriber);

        $this->handler->handle([
            'subscriber' => $subscriber,
            'ruleId' => 42,
        ]);
    }

    public function testHandleDoesNothingWhenNoSubscriber(): void
    {
        $this->logger->expects($this->never())->method('info');
        $this->subscriberManager->expects($this->never())->method('deleteSubscriber');

        $this->handler->handle([
            'ruleId' => 1,
        ]);
    }
}
