<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service;

use PhpList\Core\Domain\Messaging\Service\RateLimitedCampaignMailer;
use PhpList\Core\Domain\Messaging\Service\SendRateLimiter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class RateLimitedCampaignMailerTest extends TestCase
{
    private MailerInterface|MockObject $mailer;
    private SendRateLimiter|MockObject $limiter;

    private RateLimitedCampaignMailer $sut;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->limiter = $this->createMock(SendRateLimiter::class);
        $this->sut = new RateLimitedCampaignMailer($this->mailer, $this->limiter);
    }

    public function testSendUsesLimiterAroundMailer(): void
    {
        $email = (new Email())->to('someone@example.com');

        $this->limiter->expects($this->once())->method('awaitTurn');
        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf(Email::class));
        $this->limiter->expects($this->once())->method('afterSend');

        $this->sut->send($email);
    }
}
