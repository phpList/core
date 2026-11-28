<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\MessageHandler;

use PhpList\Core\Domain\Messaging\Message\SubscriberConfirmationMessage;
use PhpList\Core\Domain\Messaging\MessageHandler\SubscriberConfirmationMessageHandler;
use PhpList\Core\Domain\Messaging\Service\EmailService;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Resolver\SubscriberLocaleResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Email;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

class SubscriberConfirmationMessageHandlerTest extends TestCase
{
    private SubscriberConfirmationMessageHandler $handler;
    private EmailService&MockObject $emailService;
    private SubscriberRepository&MockObject $subscriberRepository;
    private SubscriberLocaleResolver&MockObject $localeResolver;
    private string $confirmationUrl = 'https://example.com/confirm';

    protected function setUp(): void
    {
        $this->emailService = $this->createMock(EmailService::class);
        $this->subscriberRepository = $this->createMock(SubscriberRepository::class);
        $this->localeResolver = $this->createMock(SubscriberLocaleResolver::class);
        $this->handler = $this->createHandler(new Translator('en'));
    }

    private function createHandler(Translator $translator): SubscriberConfirmationMessageHandler
    {
        return new SubscriberConfirmationMessageHandler(
            emailService: $this->emailService,
            translator: $translator,
            subscriberRepository: $this->subscriberRepository,
            localeResolver: $this->localeResolver,
            confirmationUrl: $this->confirmationUrl
        );
    }

    private function germanTranslator(): Translator
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['Please confirm your subscription' => 'Bitte bestätigen'], 'de');

        return $translator;
    }

    public function testSubjectUsesLocaleResolvedFromSubscriber(): void
    {
        $subscriber = $this->createMock(Subscriber::class);
        $this->subscriberRepository->method('findOneByUniqueId')->with('abc123')->willReturn($subscriber);
        $this->localeResolver->method('resolve')->with($subscriber)->willReturn('de');
        $handler = $this->createHandler($this->germanTranslator());

        $this->emailService->expects($this->once())
            ->method('sendEmail')
            ->with($this->callback(function (Email $email) {
                $this->assertSame('Bitte bestätigen', $email->getSubject());
                return true;
            }));

        $handler->__invoke(new SubscriberConfirmationMessage('a@example.com', 'abc123'));
    }

    public function testExplicitMessageLocaleWinsOverResolver(): void
    {
        $this->subscriberRepository->expects($this->never())->method('findOneByUniqueId');
        $handler = $this->createHandler($this->germanTranslator());

        $this->emailService->expects($this->once())
            ->method('sendEmail')
            ->with($this->callback(function (Email $email) {
                $this->assertSame('Bitte bestätigen', $email->getSubject());
                return true;
            }));

        $handler->__invoke(new SubscriberConfirmationMessage('a@example.com', 'abc123', false, 'de'));
    }

    public function testUnknownSubscriberUsesDefaultLocale(): void
    {
        $this->subscriberRepository->method('findOneByUniqueId')->willReturn(null);
        $this->localeResolver->expects($this->never())->method('resolve');

        $this->emailService->expects($this->once())
            ->method('sendEmail')
            ->with($this->callback(function (Email $email) {
                $this->assertSame('Please confirm your subscription', $email->getSubject());
                return true;
            }));

        $this->handler->__invoke(new SubscriberConfirmationMessage('a@example.com', 'abc123'));
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
