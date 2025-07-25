<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\MessageHandler;

use PhpList\Core\Domain\Messaging\Message\PasswordResetMessage;
use PhpList\Core\Domain\Messaging\MessageHandler\PasswordResetMessageHandler;
use PhpList\Core\Domain\Messaging\Service\EmailService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Email;

class PasswordResetMessageHandlerTest extends TestCase
{
    private PasswordResetMessageHandler $handler;
    private EmailService&MockObject $emailService;
    private string $passwordResetUrl = 'https://example.com/reset-password';

    protected function setUp(): void
    {
        $this->emailService = $this->createMock(EmailService::class);
        $this->handler = new PasswordResetMessageHandler($this->emailService, $this->passwordResetUrl);
    }

    public function testInvoke(): void
    {
        $userEmail = 'user@example.com';
        $token = 'abc123xyz789';
        $message = new PasswordResetMessage($userEmail, $token);

        $this->emailService->expects($this->once())
            ->method('sendEmail')
            ->with($this->callback(function (Email $email) use ($userEmail, $token) {
                $this->assertEquals([$userEmail], $this->getEmailAddresses($email->getTo()));
                $this->assertEquals('Password Reset Request', $email->getSubject());

                $textContent = $email->getTextBody();
                $this->assertStringContainsString(
                    'A password reset has been requested for your account',
                    $textContent
                );
                $this->assertStringContainsString($token, $textContent);

                $htmlContent = $email->getHtmlBody();
                $this->assertStringContainsString('<p>Password Reset Request!</p>', $htmlContent);
                $this->assertStringContainsString(
                    'A password reset has been requested for your account',
                    $htmlContent
                );
                
                $expectedLink = $this->passwordResetUrl . '?uniqueId=' . urlencode($token);
                $this->assertStringContainsString('<a href="' . $expectedLink . '">Reset Password</a>', $htmlContent);

                return true;
            }));

        $this->handler->__invoke($message);
    }

    /**
     * Helper method to extract email addresses from Address objects
     */
    private function getEmailAddresses(array $addresses): array
    {
        return array_map(function ($address) {
            return $address->getAddress();
        }, $addresses);
    }
}
