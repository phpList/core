<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\MessageHandler;

use PhpList\Core\Domain\Messaging\Message\SubscriberConfirmationMessage;
use PhpList\Core\Domain\Messaging\MessageHandler\SubscriberConfirmationMessageHandler;
use PhpList\Core\Domain\Messaging\Service\EmailService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Email;
use Symfony\Component\Translation\Translator;

class SubscriberConfirmationMessageHandlerTest extends TestCase
{
    private SubscriberConfirmationMessageHandler $handler;
    private EmailService&MockObject $emailService;
    private string $confirmationUrl = 'https://example.com/confirm';

    protected function setUp(): void
    {
        $this->emailService = $this->createMock(EmailService::class);
        $this->handler = new SubscriberConfirmationMessageHandler(
            emailService: $this->emailService,
            translator: new Translator('en'),
            confirmationUrl: $this->confirmationUrl
        );
    }

    public function testInvokeWithTextEmail(): void
    {
        $subscriberEmail = 'subscriber@example.com';
        $uniqueId = 'abc123';
        $message = new SubscriberConfirmationMessage($subscriberEmail, $uniqueId, false);

        $this->emailService->expects($this->once())
            ->method('sendEmail')
            ->with($this->callback(function (Email $email) use ($subscriberEmail, $uniqueId) {
                $this->assertEquals([$subscriberEmail], $this->getEmailAddresses($email->getTo()));
                $this->assertEquals('Please confirm your subscription', $email->getSubject());

                $textContent = $email->getTextBody();
                $this->assertStringContainsString('Thank you for subscribing', $textContent);
                $this->assertStringContainsString($this->confirmationUrl . '?uniqueId=' . $uniqueId, $textContent);

                $this->assertEmpty($email->getHtmlBody());

                return true;
            }));

        $this->handler->__invoke($message);
    }

    public function testInvokeWithHtmlEmail(): void
    {
        $subscriberEmail = 'subscriber@example.com';
        $uniqueId = 'abc123';
        $message = new SubscriberConfirmationMessage($subscriberEmail, $uniqueId, true);

        $this->emailService->expects($this->once())
            ->method('sendEmail')
            ->with($this->callback(function (Email $email) use ($subscriberEmail, $uniqueId) {
                $this->assertEquals([$subscriberEmail], $this->getEmailAddresses($email->getTo()));
                $this->assertEquals('Please confirm your subscription', $email->getSubject());

                $textContent = $email->getTextBody();
                $this->assertStringContainsString('Thank you for subscribing', $textContent);
                $this->assertStringContainsString($this->confirmationUrl . '?uniqueId=' . $uniqueId, $textContent);

                $htmlContent = $email->getHtmlBody();
                $this->assertStringContainsString('<p>Thank you for subscribing!</p>', $htmlContent);
                $linkStart = '<a href="' . $this->confirmationUrl . '?uniqueId=' . $uniqueId . '">';
                $this->assertStringContainsString($linkStart, $htmlContent);

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
