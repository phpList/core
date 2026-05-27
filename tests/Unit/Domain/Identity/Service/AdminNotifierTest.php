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

        $subscriber = new Subscriber('john@example.com');

        $friendEmail = 'friend@example.com';
        $lists = [new SubscriberList()];

        $expectedSubject = 'Message Forwarded';

        $expectedMessage = sprintf(
            '%s tried forwarding message %d to %s but failed',
            $subscriber->getEmail(),
            42,
            $friendEmail
        );

        $translatorCalls = [];

        $this->translator
            ->expects(self::exactly(2))
            ->method('trans')
            ->willReturnCallback(
                function (
                    string $message,
                    array $params = []
                ) use (
                    &$translatorCalls,
                    $expectedSubject,
                    $expectedMessage
                ): string {
                    $translatorCalls[] = [$message, $params];

                    return match ($message) {
                        'Message Forwarded' => $expectedSubject,
                        '%subscriber% tried forwarding message %campaignId% to %email% but failed' => $expectedMessage,
                        default => 'Unknown status encountered.',
                    };
                }
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

        $this->assertSame(
            [
                [
                    'Message Forwarded',
                    [],
                ],
                [
                    '%subscriber% tried forwarding message %campaignId% to %email% but failed',
                    [
                        '%subscriber%' => $subscriber->getEmail(),
                        '%campaignId%' => 42,
                        '%email%' => $friendEmail,
                    ],
                ],
            ],
            $translatorCalls,
        );
    }
    public function testNotifyForwardSucceededSendsAdminCopyWithoutLogging(): void
    {
        $campaign = $this->createMock(Message::class);
        $campaign->method('getId')->willReturn(777);

        $subscriber = new Subscriber('alice@example.com');

        $friendEmail = 'bob@example.net';
        $lists = [new SubscriberList(), new SubscriberList()];

        $expectedSubject = 'Message Forwarded';

        $expectedMessage = sprintf(
            '%s has forwarded message %d to %s',
            $subscriber->getEmail(),
            777,
            $friendEmail
        );

        $translatorCalls = [];

        $this->translator
            ->expects(self::exactly(2))
            ->method('trans')
            ->willReturnCallback(
                function (
                    string $message,
                    array $params = []
                ) use (
                    &$translatorCalls,
                    $expectedSubject,
                    $expectedMessage
                ): string {
                    $translatorCalls[] = [$message, $params];

                    return match ($message) {
                        'Message Forwarded' => $expectedSubject,
                        '%subscriber% has forwarded message %campaignId% to %email%' => $expectedMessage,
                        default => 'Unknown status encountered.',
                    };
                }
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

        $this->assertSame(
            [
                [
                    'Message Forwarded',
                    [],
                ],
                [
                    '%subscriber% has forwarded message %campaignId% to %email%',
                    [
                        '%subscriber%' => $subscriber->getEmail(),
                        '%campaignId%' => 777,
                        '%email%' => $friendEmail,
                    ],
                ],
            ],
            $translatorCalls,
        );
    }
}
