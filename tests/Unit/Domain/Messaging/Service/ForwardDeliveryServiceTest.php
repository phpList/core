<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service;

use LogicException;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Service\ForwardDeliveryService;
use PhpList\Core\Domain\Messaging\Service\Manager\UserMessageForwardManager;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Envelope;

class ForwardDeliveryServiceTest extends TestCase
{
    private MailerInterface&MockObject $mailer;
    private UserMessageForwardManager&MockObject $forwardManager;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->forwardManager = $this->createMock(UserMessageForwardManager::class);
    }

    public function testSendUsesBounceEnvelopeAndRecipient(): void
    {
        $service = new ForwardDeliveryService(
            mailer: $this->mailer,
            messageForwardManager: $this->forwardManager,
            bounceEmail: 'bounce@example.test',
        );

        $email = (new Email())->to('friend@example.test');

        $this->mailer->expects(self::once())
            ->method('send')
            ->with(
                self::identicalTo($email),
                self::callback(function (Envelope $envelope): bool {
                    // Check that sender is the bounce address and recipient matches TO
                    return $envelope->getSender()->getAddress() === 'bounce@example.test'
                        && $envelope->getRecipients()[0]->getAddress() === 'friend@example.test';
                })
            );

        $service->send($email);
    }

    public function testSendThrowsWhenNoRecipient(): void
    {
        $service = new ForwardDeliveryService(
            mailer: $this->mailer,
            messageForwardManager: $this->forwardManager,
            bounceEmail: 'bounce@example.test',
        );
        // no recipients
        $email = new Email();

        $this->expectException(LogicException::class);
        $service->send($email);
    }

    public function testMarkSentDelegatesToManager(): void
    {
        $service = new ForwardDeliveryService(
            mailer: $this->mailer,
            messageForwardManager: $this->forwardManager,
            bounceEmail: 'bounce@example.test',
        );

        $campaign = $this->createMock(Message::class);
        $subscriber = new Subscriber('alice@example.com');
        $friendEmail = 'friend@example.test';

        $this->forwardManager->expects(self::once())
            ->method('create')
            ->with(
                subscriber: self::identicalTo($subscriber),
                campaign: self::identicalTo($campaign),
                friendEmail: $friendEmail,
                status: 'sent'
            );

        $service->markSent($campaign, $subscriber, $friendEmail);
    }

    public function testMarkFailedDelegatesToManager(): void
    {
        $service = new ForwardDeliveryService(
            mailer: $this->mailer,
            messageForwardManager: $this->forwardManager,
            bounceEmail: 'bounce@example.test',
        );

        $campaign = $this->createMock(Message::class);
        $subscriber = new Subscriber('alice@example.com');
        $friendEmail = 'friend@example.test';

        $this->forwardManager->expects(self::once())
            ->method('create')
            ->with(
                subscriber: self::identicalTo($subscriber),
                campaign: self::identicalTo($campaign),
                friendEmail: $friendEmail,
                status: 'failed'
            );

        $service->markFailed($campaign, $subscriber, $friendEmail);
    }
}
