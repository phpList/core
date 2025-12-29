<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service;

use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Messaging\Exception\MessageSizeLimitExceededException;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Service\MailSizeChecker;
use PhpList\Core\Domain\Messaging\Service\Manager\MessageDataManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Mime\Email;

class MailSizeCheckerTest extends TestCase
{
    private EventLogManager&MockObject $eventLogManager;
    private MessageDataManager&MockObject $messageDataManager;
    private CacheInterface&MockObject $cache;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->eventLogManager = $this->createMock(EventLogManager::class);
        $this->messageDataManager = $this->createMock(MessageDataManager::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function createMessageWithId(int $id): Message
    {
        $message = $this->getMockBuilder(Message::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->getMock();

        $message->method('getId')->willReturn($id);

        return $message;
    }

    private function createEmail(): Email
    {
        return (new Email())
            ->from('no-reply@example.com')
            ->to('user@example.com')
            ->subject('Subject')
            ->text('Body');
    }

    public function testDisabledMaxMailSizeDoesNothingAndSkipsCache(): void
    {
        $checker = new MailSizeChecker(
            eventLogManager: $this->eventLogManager,
            messageDataManager: $this->messageDataManager,
            cache: $this->cache,
            logger: $this->logger,
            maxMailSize: 0,
        );

        $this->cache->expects($this->never())->method('has');
        $this->messageDataManager->expects($this->never())->method('setMessageData');
        $this->eventLogManager->expects($this->never())->method('log');
        $this->logger->expects($this->never())->method('warning');

        $checker->__invoke($this->createMessageWithId(1), $this->createEmail(), true);
        // No exceptions
        $this->addToAssertionCount(1);
    }

    public function testCacheMissCalculatesAndStoresAndDoesNotThrow(): void
    {
        // very large to avoid throwing regardless of calculated size
        $checker = new MailSizeChecker(
            eventLogManager: $this->eventLogManager,
            messageDataManager: $this->messageDataManager,
            cache: $this->cache,
            logger: $this->logger,
            maxMailSize: 10_000_000,
        );

        $message = $this->createMessageWithId(42);

        $this->cache->expects($this->once())
            ->method('has')
            ->with($this->callback(fn (string $key) => str_contains($key, 'messaging.size.42.htmlsize')))
            ->willReturn(false);

        $this->messageDataManager->expects($this->once())
            ->method('setMessageData')
            ->with($message, 'htmlsize', $this->callback(fn ($v) => is_int($v) && $v > 0));

        $this->cache->expects($this->once())
            ->method('set')
            ->with(
                $this->callback(fn (string $key) => str_contains($key, 'messaging.size.42.htmlsize')),
                $this->callback(fn ($v) => is_int($v) && $v > 0)
            );

        // After setting, get() will be called; return a small size to keep below limit
        $this->cache->expects($this->once())
            ->method('get')
            ->with($this->callback(fn (string $key) => str_contains($key, 'messaging.size.42.htmlsize')))
            ->willReturn(100);

        $checker->__invoke($message, $this->createEmail(), true);
        $this->addToAssertionCount(1);
    }

    public function testThrowsWhenCachedSizeExceedsLimitAndLogsAndEvents(): void
    {
        $checker = new MailSizeChecker(
            eventLogManager: $this->eventLogManager,
            messageDataManager: $this->messageDataManager,
            cache: $this->cache,
            logger: $this->logger,
            maxMailSize: 500,
        );

        $message = $this->createMessageWithId(7);

        // Simulate cache hit with a large size
        $this->cache->method('has')->willReturn(true);
        $this->cache->method('get')->willReturn(1_000);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->callback(
                fn (string $msg) => str_contains($msg, 'Message too large') && str_contains($msg, '7')
            ));

        $this->eventLogManager->expects($this->exactly(2))
            ->method('log')
            ->with(
                'send',
                $this->callback(
                    fn (string $msg) =>
                    str_contains($msg, 'Message too large') || str_contains($msg, 'Campaign 7 suspended')
                )
            );

        $this->expectException(MessageSizeLimitExceededException::class);
        $checker->__invoke($message, $this->createEmail(), false);
    }

    public function testReturnsWhenCachedSizeWithinLimit(): void
    {
        $checker = new MailSizeChecker(
            eventLogManager: $this->eventLogManager,
            messageDataManager: $this->messageDataManager,
            cache: $this->cache,
            logger: $this->logger,
            maxMailSize: 10_000,
        );

        $message = $this->createMessageWithId(99);

        $this->cache->method('has')->willReturn(true);
        // well below the limit
        $this->cache->method('get')->willReturn(123);

        $this->logger->expects($this->never())->method('warning');
        $this->eventLogManager->expects($this->never())->method('log');

        $checker->__invoke($message, $this->createEmail(), true);
        $this->addToAssertionCount(1);
    }
}
