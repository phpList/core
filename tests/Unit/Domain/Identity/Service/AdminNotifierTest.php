<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Identity\Service;

use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Identity\Service\AdminCopyEmailSender;
use PhpList\Core\Domain\Identity\Service\AdminNotifier;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberList;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AdminNotifierTest extends TestCase
{
    private AdminCopyEmailSender&MockObject $adminCopyEmailSender;
    private TranslatorInterface&MockObject $translator;
    private EventLogManager&MockObject $eventLogManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminCopyEmailSender = $this->createMock(AdminCopyEmailSender::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->eventLogManager = $this->createMock(EventLogManager::class);
    }

    public function testNotifyForwardFailedSendsAdminCopyAndLogs(): void
    {
        $campaign = $this->createMock(Message::class);
        $campaign->method('getId')->willReturn(42);

        $subscriber = new Subscriber();
        $subscriber->setEmail('john@example.com');

        $friendEmail = 'friend@example.com';
        $lists = [new SubscriberList()];

        $expectedSubject = 'Message Forwarded';
        $expectedMessage = sprintf(
            '%s tried forwarding message %d to %s but failed',
            $subscriber->getEmail(),
            42,
            $friendEmail
        );

        // Translator expectations: first for subject, then for message with placeholders
        $this->translator
            ->expects(self::exactly(2))
            ->method('trans')
            ->withConsecutive(
                [$this->equalTo('Message Forwarded')],
                [
                    $this->equalTo('%subscriber% tried forwarding message %campaignId% to %email% but failed'),
                    $this->callback(function (array $params) use ($subscriber, $friendEmail): bool {
                        return ($params['%subscriber%'] ?? null) === $subscriber->getEmail()
                            && ($params['%campaignId%'] ?? null) === 42
                            && ($params['%email%'] ?? null) === $friendEmail;
                    })
                ]
            )
            ->willReturnOnConsecutiveCalls(
                $expectedSubject,
                $expectedMessage
            );

        // Admin copy sender should be invoked with translated subject and message and same lists
        $this->adminCopyEmailSender
            ->expects(self::once())
            ->method('__invoke')
            ->with(
                $this->equalTo($expectedSubject),
                $this->equalTo($expectedMessage),
                $this->identicalTo($lists)
            );

        // EventLogManager should log only on failure
        $this->eventLogManager
            ->expects(self::once())
            ->method('log')
            ->with(
                $this->equalTo('forward'),
                $this->equalTo('Error loading message 42 in cache')
            );

        $notifier = new AdminNotifier(
            adminCopyEmailSender: $this->adminCopyEmailSender,
            translator: $this->translator,
            eventLogManager: $this->eventLogManager,
        );

        $notifier->notifyForwardFailed(
            campaign: $campaign,
            forwardingSubscriber: $subscriber,
            friendEmail: $friendEmail,
            lists: $lists
        );
    }

    public function testNotifyForwardSucceededSendsAdminCopyWithoutLogging(): void
    {
        $campaign = $this->createMock(Message::class);
        $campaign->method('getId')->willReturn(777);

        $subscriber = new Subscriber();
        $subscriber->setEmail('alice@example.com');

        $friendEmail = 'bob@example.net';
        $lists = [new SubscriberList(), new SubscriberList()];

        $expectedSubject = 'Message Forwarded';
        $expectedMessage = sprintf(
            '%s has forwarded message %d to %s',
            $subscriber->getEmail(),
            777,
            $friendEmail
        );

        $this->translator
            ->expects(self::exactly(2))
            ->method('trans')
            ->withConsecutive(
                [$this->equalTo('Message Forwarded')],
                [
                    $this->equalTo('%subscriber% has forwarded message %campaignId% to %email%'),
                    $this->callback(function (array $params) use ($subscriber, $friendEmail): bool {
                        return ($params['%subscriber%'] ?? null) === $subscriber->getEmail()
                            && ($params['%campaignId%'] ?? null) === 777
                            && ($params['%email%'] ?? null) === $friendEmail;
                    })
                ]
            )
            ->willReturnOnConsecutiveCalls(
                $expectedSubject,
                $expectedMessage
            );

        $this->adminCopyEmailSender
            ->expects(self::once())
            ->method('__invoke')
            ->with(
                $this->equalTo($expectedSubject),
                $this->equalTo($expectedMessage),
                $this->identicalTo($lists)
            );

        $this->eventLogManager
            ->expects(self::never())
            ->method('log');

        $notifier = new AdminNotifier(
            adminCopyEmailSender: $this->adminCopyEmailSender,
            translator: $this->translator,
            eventLogManager: $this->eventLogManager,
        );

        $notifier->notifyForwardSucceeded(
            campaign: $campaign,
            forwardingSubscriber: $subscriber,
            friendEmail: $friendEmail,
            lists: $lists
        );
    }
}
