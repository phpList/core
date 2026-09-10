<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service;

use PhpList\Core\Domain\Messaging\Exception\MessageCacheMissingException;
use PhpList\Core\Domain\Messaging\Model\Dto\DomainThrottleResult;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\UserMessageStatus;
use PhpList\Core\Domain\Messaging\Model\UserMessage;
use PhpList\Core\Domain\Messaging\Repository\UserMessageRepository;
use PhpList\Core\Domain\Messaging\Service\CampaignEmailSender;
use PhpList\Core\Domain\Messaging\Service\CampaignSendingLoop;
use PhpList\Core\Domain\Messaging\Service\DomainRateLimiter;
use PhpList\Core\Domain\Messaging\Service\MaxProcessTimeLimiter;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

class CampaignSendingLoopTest extends TestCase
{
    private UserMessageRepository|MockObject $userMessageRepository;
    private MaxProcessTimeLimiter|MockObject $timeLimiter;
    private DomainRateLimiter|MockObject $domainRateLimiter;
    private CacheInterface|MockObject $cache;
    private CampaignEmailSender|MockObject $emailSender;
    private CampaignSendingLoop $loop;
    private Message|MockObject $campaign;

    protected function setUp(): void
    {
        $this->userMessageRepository = $this->createMock(UserMessageRepository::class);
        $this->timeLimiter = $this->createMock(MaxProcessTimeLimiter::class);
        $this->domainRateLimiter = $this->createMock(DomainRateLimiter::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->emailSender = $this->createMock(CampaignEmailSender::class);

        $this->loop = new CampaignSendingLoop(
            $this->userMessageRepository,
            $this->timeLimiter,
            $this->domainRateLimiter,
            $this->cache,
            $this->emailSender,
        );

        $this->campaign = $this->createMock(Message::class);
    }

    public function testRunReturnsFalseAndSendsToEachEligibleSubscriber(): void
    {
        $this->timeLimiter->method('shouldStop')->willReturn(false);
        $this->domainRateLimiter->method('attemptSend')
            ->willReturn(new DomainThrottleResult(allowed: true, domain: null));

        $precached = new MessagePrecacheDto();
        $this->cache->method('get')->willReturn($precached);

        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->method('getEmail')->willReturn('test@example.com');

        $this->userMessageRepository->method('findByUserAndMessage')->willReturn(null);

        $this->emailSender->expects($this->once())
            ->method('send')
            ->with($this->campaign, $subscriber, $this->isInstanceOf(UserMessage::class), $precached);

        $stoppedEarly = $this->loop->run($this->campaign, [$subscriber], 'cache-key');

        $this->assertFalse($stoppedEarly);
    }

    public function testRunStopsEarlyWhenTimeLimitReached(): void
    {
        $this->timeLimiter->method('shouldStop')->willReturn(true);

        $subscriber = $this->createMock(Subscriber::class);
        $this->emailSender->expects($this->never())->method('send');

        $stoppedEarly = $this->loop->run($this->campaign, [$subscriber], 'cache-key');

        $this->assertTrue($stoppedEarly);
    }

    public function testRunStopsEarlyAndLeavesNoUserMessageWhenDomainThrottled(): void
    {
        $this->domainRateLimiter->method('attemptSend')
            ->willReturn(new DomainThrottleResult(allowed: false, domain: 'example.com', blockedAttempts: 1));

        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->method('getEmail')->willReturn('throttled@example.com');

        $this->userMessageRepository->expects($this->never())->method('save');
        $this->emailSender->expects($this->never())->method('send');

        $stoppedEarly = $this->loop->run($this->campaign, [$subscriber], 'cache-key');

        $this->assertTrue($stoppedEarly);
    }

    public function testRunSkipsSubscriberWithExistingNonTodoUserMessage(): void
    {
        $this->timeLimiter->method('shouldStop')->willReturn(false);

        $subscriber = $this->createMock(Subscriber::class);

        $existing = $this->createMock(UserMessage::class);
        $existing->method('getStatus')->willReturn(UserMessageStatus::Sent);
        $this->userMessageRepository->method('findByUserAndMessage')->willReturn($existing);

        $this->userMessageRepository->expects($this->never())->method('save');
        $this->emailSender->expects($this->never())->method('send');

        $stoppedEarly = $this->loop->run($this->campaign, [$subscriber], 'cache-key');

        $this->assertFalse($stoppedEarly);
    }

    public function testRunDelegatesInvalidEmailToEmailSender(): void
    {
        $this->timeLimiter->method('shouldStop')->willReturn(false);
        $this->domainRateLimiter->method('attemptSend')
            ->willReturn(new DomainThrottleResult(allowed: true, domain: null));

        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->method('getEmail')->willReturn('not-an-email');

        $this->userMessageRepository->method('findByUserAndMessage')->willReturn(null);

        $this->emailSender->expects($this->once())
            ->method('handleInvalidEmail')
            ->with($this->isInstanceOf(UserMessage::class), $subscriber, $this->campaign);
        $this->emailSender->expects($this->never())->method('send');

        $this->loop->run($this->campaign, [$subscriber], 'cache-key');
    }

    public function testRunThrowsWhenPrecachedMessageMissingFromCache(): void
    {
        $this->timeLimiter->method('shouldStop')->willReturn(false);
        $this->domainRateLimiter->method('attemptSend')
            ->willReturn(new DomainThrottleResult(allowed: true, domain: null));

        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->method('getEmail')->willReturn('test@example.com');

        $this->userMessageRepository->method('findByUserAndMessage')->willReturn(null);
        $this->cache->method('get')->willReturn(null);

        $this->expectException(MessageCacheMissingException::class);

        $this->loop->run($this->campaign, [$subscriber], 'cache-key');
    }
}
