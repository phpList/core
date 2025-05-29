<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service;

use PhpList\Core\Domain\Messaging\Service\EmailService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class EmailServiceTest extends TestCase
{
    private EmailService $emailService;
    private MailerInterface&MockObject $mailer;
    private string $defaultFromEmail = 'default@example.com';

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->emailService = new EmailService($this->mailer, $this->defaultFromEmail);
    }

    public function testSendEmailWithDefaultFrom(): void
    {
        $email = (new Email())
            ->to('recipient@example.com')
            ->subject('Test Subject')
            ->text('Test Content');

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $sentEmail) {
                $fromAddresses = $sentEmail->getFrom();
                $this->assertCount(1, $fromAddresses);
                $this->assertEquals($this->defaultFromEmail, $fromAddresses[0]->getAddress());
                return true;
            }));

        $this->emailService->sendEmail($email);
    }

    public function testSendEmailWithCustomFrom(): void
    {
        $customFrom = 'custom@example.com';
        $email = (new Email())
            ->from($customFrom)
            ->to('recipient@example.com')
            ->subject('Test Subject')
            ->text('Test Content');

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $sentEmail) use ($customFrom) {
                $fromAddresses = $sentEmail->getFrom();
                $this->assertCount(1, $fromAddresses);
                $this->assertEquals($customFrom, $fromAddresses[0]->getAddress());
                return true;
            }));

        $this->emailService->sendEmail($email);
    }

    public function testSendEmailWithCcBccAndReplyTo(): void
    {
        $email = (new Email())
            ->to('recipient@example.com')
            ->subject('Test Subject')
            ->text('Test Content');

        $cc = ['cc@example.com'];
        $bcc = ['bcc@example.com'];
        $replyTo = ['reply@example.com'];

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $sentEmail) use ($cc, $bcc, $replyTo) {
                $ccAddresses = $sentEmail->getCc();
                $bccAddresses = $sentEmail->getBcc();
                $replyToAddresses = $sentEmail->getReplyTo();

                $this->assertCount(1, $ccAddresses);
                $this->assertEquals($cc[0], $ccAddresses[0]->getAddress());

                $this->assertCount(1, $bccAddresses);
                $this->assertEquals($bcc[0], $bccAddresses[0]->getAddress());

                $this->assertCount(1, $replyToAddresses);
                $this->assertEquals($replyTo[0], $replyToAddresses[0]->getAddress());

                return true;
            }));

        $this->emailService->sendEmail($email, $cc, $bcc, $replyTo);
    }

    public function testSendEmailWithAttachments(): void
    {
        $email = (new Email())
            ->to('recipient@example.com')
            ->subject('Test Subject')
            ->text('Test Content');

        $attachments = ['/path/to/attachment.pdf'];

        $this->mailer->expects($this->once())
            ->method('send');

        $this->emailService->sendEmail($email, [], [], [], $attachments);
    }

    public function testSendBulkEmail(): void
    {
        $recipients = ['user1@example.com', 'user2@example.com', 'user3@example.com'];
        $subject = 'Bulk Test Subject';
        $text = 'Bulk Test Content';
        $html = '<p>Bulk Test HTML Content</p>';
        $from = 'sender@example.com';
        $fromName = 'Sender Name';

        $this->mailer->expects($this->exactly(count($recipients)))
            ->method('send')
            ->with($this->callback(function (Email $sentEmail) use ($subject, $text, $html, $from, $fromName) {
                $this->assertEquals($subject, $sentEmail->getSubject());
                $this->assertEquals($text, $sentEmail->getTextBody());
                $this->assertEquals($html, $sentEmail->getHtmlBody());
                
                $fromAddresses = $sentEmail->getFrom();
                $this->assertCount(1, $fromAddresses);
                $this->assertEquals($from, $fromAddresses[0]->getAddress());
                $this->assertEquals($fromName, $fromAddresses[0]->getName());
                
                return true;
            }));

        $this->emailService->sendBulkEmail($recipients, $subject, $text, $html, $from, $fromName);
    }

    public function testSendBulkEmailWithDefaultFrom(): void
    {
        $recipients = ['user1@example.com', 'user2@example.com'];
        $subject = 'Bulk Test Subject';
        $text = 'Bulk Test Content';

        $this->mailer->expects($this->exactly(count($recipients)))
            ->method('send')
            ->with($this->callback(function (Email $sentEmail) {
                $fromAddresses = $sentEmail->getFrom();
                $this->assertCount(1, $fromAddresses);
                $this->assertEquals($this->defaultFromEmail, $fromAddresses[0]->getAddress());
                return true;
            }));

        $this->emailService->sendBulkEmail($recipients, $subject, $text);
    }
}
