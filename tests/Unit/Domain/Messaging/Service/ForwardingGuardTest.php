<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service;

use DateTimeImmutable;
use PhpList\Core\Domain\Messaging\Exception\ForwardLimitExceededException;
use PhpList\Core\Domain\Messaging\Exception\MessageNotReceivedException;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\UserMessageForward;
use PhpList\Core\Domain\Messaging\Model\UserMessage;
use PhpList\Core\Domain\Messaging\Repository\UserMessageForwardRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageRepository;
use PhpList\Core\Domain\Messaging\Service\ForwardingGuard;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ForwardingGuardTest extends TestCase
{
    private SubscriberRepository&MockObject $subscriberRepo;
    private UserMessageRepository&MockObject $userMessageRepo;
    private UserMessageForwardRepository&MockObject $forwardRepo;

    protected function setUp(): void
    {
        $this->subscriberRepo = $this->createMock(SubscriberRepository::class);
        $this->userMessageRepo = $this->createMock(UserMessageRepository::class);
        $this->forwardRepo = $this->createMock(UserMessageForwardRepository::class);
    }

    public function testAssertCanForwardReturnsSubscriber(): void
    {
        $guard = new ForwardingGuard(
            subscriberRepository: $this->subscriberRepo,
            userMessageRepository: $this->userMessageRepo,
            forwardRepository: $this->forwardRepo,
            forwardMessageCount: 2,
            forwardEmailPeriod: '1 day',
        );

        $uid = 'abc';
        $campaign = $this->createMock(Message::class);
        $subscriber = new Subscriber('alice@example.com');

        $this->subscriberRepo->method('findOneByUniqueId')->with($uid)->willReturn($subscriber);
        $this->userMessageRepo->method('findByUserAndMessage')->willReturn(
            $this->createMock(UserMessage::class)
        );
        $this->forwardRepo->method('getCountByUserSince')->willReturn(1);

        $result = $guard->assertCanForward($uid, $campaign);
        self::assertSame($subscriber, $result);
    }

    public function testAssertCanForwardThrowsWhenSubscriberMissing(): void
    {
        $guard = new ForwardingGuard(
            subscriberRepository: $this->subscriberRepo,
            userMessageRepository: $this->userMessageRepo,
            forwardRepository: $this->forwardRepo,
            forwardMessageCount: 2,
            forwardEmailPeriod: '1 day',
        );

        $this->subscriberRepo->method('findOneByUniqueId')->willReturn(null);

        $this->expectException(MessageNotReceivedException::class);
        $guard->assertCanForward('uid', $this->createMock(Message::class));
    }

    public function testAssertCanForwardThrowsWhenMessageNotReceived(): void
    {
        $guard = new ForwardingGuard(
            subscriberRepository: $this->subscriberRepo,
            userMessageRepository: $this->userMessageRepo,
            forwardRepository: $this->forwardRepo,
            forwardMessageCount: 2,
            forwardEmailPeriod: '1 day',
        );

        $this->subscriberRepo->method('findOneByUniqueId')->willReturn(new Subscriber('alice@example.com'));
        $this->userMessageRepo->method('findByUserAndMessage')->willReturn(null);

        $this->expectException(MessageNotReceivedException::class);
        $guard->assertCanForward('uid', $this->createMock(Message::class));
    }

    public function testAssertCanForwardThrowsWhenLimitExceeded(): void
    {
        $guard = new ForwardingGuard(
            subscriberRepository: $this->subscriberRepo,
            userMessageRepository: $this->userMessageRepo,
            forwardRepository: $this->forwardRepo,
            forwardMessageCount: 2,
            forwardEmailPeriod: '1 day',
        );

        $this->subscriberRepo->method('findOneByUniqueId')->willReturn(new Subscriber('alice@example.com'));
        $this->userMessageRepo->method('findByUserAndMessage')->willReturn($this->createMock(UserMessage::class));
        $this->forwardRepo->method('getCountByUserSince')->willReturn(2);

        $this->expectException(ForwardLimitExceededException::class);
        $guard->assertCanForward('uid', $this->createMock(Message::class));
    }

    public function testHasAlreadyBeenSentTrue(): void
    {
        $guard = new ForwardingGuard(
            subscriberRepository: $this->subscriberRepo,
            userMessageRepository: $this->userMessageRepo,
            forwardRepository: $this->forwardRepo,
            forwardMessageCount: 10,
            forwardEmailPeriod: '1 day',
        );

        $campaign = $this->createMock(Message::class);
        $campaign->method('getId')->willReturn(7);

        $forward = (new UserMessageForward())->setStatus('sent');

        $this->forwardRepo->method('findByEmailAndMessage')->with('friend@x.tld', 7)->willReturn($forward);

        self::assertTrue($guard->hasAlreadyBeenSent('friend@x.tld', $campaign));
    }

    public function testHasAlreadyBeenSentFalseWhenNone(): void
    {
        $guard = new ForwardingGuard(
            subscriberRepository: $this->subscriberRepo,
            userMessageRepository: $this->userMessageRepo,
            forwardRepository: $this->forwardRepo,
            forwardMessageCount: 10,
            forwardEmailPeriod: '1 day',
        );

        $campaign = $this->createMock(Message::class);
        $campaign->method('getId')->willReturn(8);

        $this->forwardRepo->method('findByEmailAndMessage')->willReturn(null);

        self::assertFalse($guard->hasAlreadyBeenSent('f@x.tld', $campaign));
    }
}
