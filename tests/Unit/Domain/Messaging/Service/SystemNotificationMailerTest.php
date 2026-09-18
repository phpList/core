<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service;

use PhpList\Core\Domain\Messaging\Service\Builder\SystemEmailBuilder;
use PhpList\Core\Domain\Messaging\Service\SystemNotificationMailer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class SystemNotificationMailerTest extends TestCase
{
    private SystemEmailBuilder|MockObject $systemEmailBuilder;
    private MailerInterface|MockObject $mailer;
    private SystemNotificationMailer $notificationMailer;

    protected function setUp(): void
    {
        $this->systemEmailBuilder = $this->createMock(SystemEmailBuilder::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->notificationMailer = new SystemNotificationMailer(
            $this->systemEmailBuilder,
            $this->mailer,
            'bounce@example.com',
        );
    }

    public function testSendBuildsAndSendsEmailWithBounceEnvelope(): void
    {
        $email = (new Email())->to('admin@example.com')->subject('Campaign started')->text('body');

        $this->systemEmailBuilder->expects($this->once())
            ->method('buildCampaignEmail')
            ->with(1, $this->anything(), 'admin@example.com')
            ->willReturn($email);

        $this->mailer->expects($this->once())
            ->method('send')
            ->with(
                $this->identicalTo($email),
                $this->callback(function (Envelope $envelope): bool {
                    $this->assertSame('bounce@example.com', $envelope->getSender()->getAddress());
                    $this->assertSame('admin@example.com', $envelope->getRecipients()[0]->getAddress());

                    return true;
                })
            );

        $result = $this->notificationMailer->send(1, 'admin@example.com', 'Campaign started', 'body');

        $this->assertTrue($result);
    }

    public function testSendReturnsFalseWithoutSendingWhenBuilderReturnsNull(): void
    {
        $this->systemEmailBuilder->method('buildCampaignEmail')->willReturn(null);

        $this->mailer->expects($this->never())->method('send');

        $result = $this->notificationMailer->send(1, 'admin@example.com', 'Campaign started', 'body');

        $this->assertFalse($result);
    }
}
