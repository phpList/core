<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Service\CampaignAdminNotifier;
use PhpList\Core\Domain\Messaging\Service\SystemNotificationMailer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

class CampaignAdminNotifierTest extends TestCase
{
    private SystemNotificationMailer|MockObject $notificationMailer;
    private EntityManagerInterface|MockObject $entityManager;
    private TranslatorInterface|MockObject $translator;
    private LoggerInterface|MockObject $logger;
    private CampaignAdminNotifier $notifier;

    protected function setUp(): void
    {
        $this->notificationMailer = $this->createMock(SystemNotificationMailer::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->translator = $this->createMock(Translator::class);
        $this->translator->method('trans')->willReturnCallback(fn (string $msg) => $msg);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->notifier = new CampaignAdminNotifier(
            $this->notificationMailer,
            $this->entityManager,
            $this->translator,
            $this->logger,
        );
    }

    public function testNotifyStartSendsToEachConfiguredAddressAndRecordsStartNotified(): void
    {
        $campaign = $this->createMock(Message::class);
        $campaign->method('getId')->willReturn(1);

        $this->notificationMailer->expects($this->exactly(2))
            ->method('send')
            ->willReturnCallback(function (int $messageId, string $toEmail) {
                $this->assertSame(1, $messageId);
                $this->assertContains($toEmail, ['admin1@example.com', 'admin2@example.com']);

                return true;
            });

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $this->notifier->notifyStart($campaign, [
            'notify_start' => 'admin1@example.com,admin2@example.com',
            'subject' => 'Hello',
        ], 1);
    }

    public function testNotifyStartDoesNothingWhenNotifyStartMissing(): void
    {
        $campaign = $this->createMock(Message::class);

        $this->notificationMailer->expects($this->never())->method('send');
        $this->entityManager->expects($this->never())->method('persist');

        $this->notifier->notifyStart($campaign, [], 1);
    }

    public function testNotifyStartDoesNothingWhenAlreadyNotified(): void
    {
        $campaign = $this->createMock(Message::class);

        $this->notificationMailer->expects($this->never())->method('send');

        $this->notifier->notifyStart($campaign, [
            'notify_start' => 'admin1@example.com',
            'start_notified' => '2024-01-01 00:00:00',
        ], 1);
    }
}
