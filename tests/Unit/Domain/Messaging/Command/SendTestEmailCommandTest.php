<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Command;

use Exception;
use PhpList\Core\Domain\Messaging\Command\SendTestEmailCommand;
use PhpList\Core\Domain\Messaging\Service\EmailService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mime\Email;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

class SendTestEmailCommandTest extends TestCase
{
    private EmailService&MockObject $emailService;
    private CommandTester $commandTester;
    private TranslatorInterface $translator;

    protected function setUp(): void
    {
        $this->emailService = $this->createMock(EmailService::class);
        $this->translator = new Translator('en');
        $command = new SendTestEmailCommand($this->emailService, $this->translator);

        $application = new Application();
        $application->add($command);

        $this->commandTester = new CommandTester($command);
    }

    public function testExecuteWithValidEmail(): void
    {
        $this->emailService->expects($this->once())
            ->method('sendEmail')
            ->with($this->callback(function (Email $email) {
                $this->assertEquals('Test Email from phpList', $email->getSubject());
                $this->assertStringContainsString('This is a test email', $email->getTextBody());
                $this->assertStringContainsString('<h1>Test</h1>', $email->getHtmlBody());

                $toAddresses = $email->getTo();
                $this->assertCount(1, $toAddresses);
                $this->assertEquals('test@example.com', $toAddresses[0]->getAddress());

                $fromAddresses = $email->getFrom();
                $this->assertCount(1, $fromAddresses);
                $this->assertEquals('admin@example.com', $fromAddresses[0]->getAddress());
                $this->assertEquals('Admin Team', $fromAddresses[0]->getName());

                return true;
            }));

        $this->commandTester->execute([
            'recipient' => 'test@example.com',
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Queuing test email for', $output);
        $this->assertStringContainsString('Test email queued successfully', $output);
        $this->assertStringContainsString('It will be sent asynchronously', $output);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithValidEmailSync(): void
    {
        $this->emailService->expects($this->once())
            ->method('sendEmailSync')
            ->with($this->callback(function (Email $email) {
                $this->assertEquals('Test Email from phpList', $email->getSubject());
                $this->assertStringContainsString('This is a test email', $email->getTextBody());
                $this->assertStringContainsString('<h1>Test</h1>', $email->getHtmlBody());

                $toAddresses = $email->getTo();
                $this->assertCount(1, $toAddresses);
                $this->assertEquals('test@example.com', $toAddresses[0]->getAddress());

                $fromAddresses = $email->getFrom();
                $this->assertCount(1, $fromAddresses);
                $this->assertEquals('admin@example.com', $fromAddresses[0]->getAddress());
                $this->assertEquals('Admin Team', $fromAddresses[0]->getName());

                return true;
            }));

        $this->commandTester->execute([
            'recipient' => 'test@example.com',
            '--sync' => true,
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Sending test email synchronously to', $output);
        $this->assertStringContainsString('Test email sent successfully', $output);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithoutRecipient(): void
    {
        $this->emailService->expects($this->never())
            ->method('sendEmail');
        $this->emailService->expects($this->never())
            ->method('sendEmailSync');

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Recipient email address not provided', $output);

        $this->assertEquals(1, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithoutRecipientSync(): void
    {
        $this->emailService->expects($this->never())
            ->method('sendEmail');
        $this->emailService->expects($this->never())
            ->method('sendEmailSync');

        $this->commandTester->execute([
            '--sync' => true,
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Recipient email address not provided', $output);

        $this->assertEquals(1, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithInvalidEmail(): void
    {
        $this->emailService->expects($this->never())
            ->method('sendEmail');
        $this->emailService->expects($this->never())
            ->method('sendEmailSync');

        $this->commandTester->execute([
            'recipient' => 'invalid-email',
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Invalid email address', $output);

        $this->assertEquals(1, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithInvalidEmailSync(): void
    {
        $this->emailService->expects($this->never())
            ->method('sendEmail');
        $this->emailService->expects($this->never())
            ->method('sendEmailSync');

        $this->commandTester->execute([
            'recipient' => 'invalid-email',
            '--sync' => true,
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Invalid email address', $output);

        $this->assertEquals(1, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithEmailServiceException(): void
    {
        $this->emailService->expects($this->once())
            ->method('sendEmail')
            ->willThrowException(new Exception('Test exception'));

        $this->commandTester->execute([
            'recipient' => 'test@example.com',
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Failed to send test email', $output);
        $this->assertStringContainsString('Test exception', $output);

        $this->assertEquals(1, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithEmailServiceExceptionSync(): void
    {
        $this->emailService->expects($this->once())
            ->method('sendEmailSync')
            ->willThrowException(new Exception('Test sync exception'));

        $this->commandTester->execute([
            'recipient' => 'test@example.com',
            '--sync' => true,
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Failed to send test email', $output);
        $this->assertStringContainsString('Test sync exception', $output);

        $this->assertEquals(1, $this->commandTester->getStatusCode());
    }
}
