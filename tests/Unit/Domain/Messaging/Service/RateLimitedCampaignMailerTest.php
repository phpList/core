<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service;

use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Messaging\Model\Message\MessageContent;
use PhpList\Core\Domain\Messaging\Model\Message\MessageFormat;
use PhpList\Core\Domain\Messaging\Model\Message\MessageMetadata;
use PhpList\Core\Domain\Messaging\Model\Message\MessageOptions;
use PhpList\Core\Domain\Messaging\Model\Message\MessageSchedule;
use PhpList\Core\Domain\Messaging\Service\RateLimitedCampaignMailer;
use PhpList\Core\Domain\Messaging\Service\SendRateLimiter;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
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

    public function testComposeEmailSetsHeadersAndBody(): void
    {
        $message = $this->buildMessage(
            subject: 'Subject',
            textBody: 'Plain text',
            htmlBody: '<p>HTML</p>',
            from: 'from@example.com',
            replyTo: 'reply@example.com'
        );

        $subscriber = new Subscriber();
        $this->setSubscriberEmail($subscriber, 'user@example.com');

        $precached = new MessagePrecacheDto();
        $precached->subject = 'Subject';
        $precached->textContent = 'Plain text';
        $precached->content = '<p>HTML</p>';

        $email = $this->sut->composeEmail($message, $subscriber, $precached);

        $this->assertInstanceOf(Email::class, $email);
        $this->assertSame('user@example.com', $email->getTo()[0]->getAddress());
        $this->assertSame('Subject', $email->getSubject());
        $this->assertSame('from@example.com', $email->getFrom()[0]->getAddress());
        $this->assertSame('reply@example.com', $email->getReplyTo()[0]->getAddress());
        $this->assertSame('Plain text', $email->getTextBody());
        $this->assertSame('<p>HTML</p>', $email->getHtmlBody());
    }

    public function testComposeEmailWithoutOptionalHeaders(): void
    {
        $message = $this->buildMessage(
            subject: 'No headers',
            textBody: 'text',
            htmlBody: '<b>h</b>',
            from: '',
            replyTo: ''
        );

        $subscriber = new Subscriber();
        $this->setSubscriberEmail($subscriber, 'user2@example.com');

        $precached = new MessagePrecacheDto();
        $precached->subject = 'No headers';
        $precached->textContent = 'text';
        $precached->content = '<b>h</b>';

        $email = $this->sut->composeEmail($message, $subscriber, $precached);

        $this->assertSame('user2@example.com', $email->getTo()[0]->getAddress());
        $this->assertSame('No headers', $email->getSubject());
        $this->assertSame([], $email->getFrom());
        $this->assertSame([], $email->getReplyTo());
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

    private function buildMessage(
        string $subject,
        string $textBody,
        string $htmlBody,
        string $from,
        string $replyTo
    ): Message {
        $content = new MessageContent(
            subject: $subject,
            text: $htmlBody,
            textMessage: $textBody,
            footer: null,
        );
        $format = new MessageFormat(
            htmlFormatted: true,
            sendFormat: MessageFormat::FORMAT_HTML,
            formatOptions: [MessageFormat::FORMAT_HTML]
        );
        $schedule = new MessageSchedule(
            repeatInterval: 0,
            repeatUntil: null,
            requeueInterval: 0,
            requeueUntil: null,
            embargo: null
        );
        $metadata = new MessageMetadata();
        $options = new MessageOptions(fromField: $from, toField: '', replyTo: $replyTo);

        return new Message($format, $schedule, $metadata, $content, $options, null, null);
    }

    /**
     * Subscriber has no public setter for email, so we use reflection.
     */
    private function setSubscriberEmail(Subscriber $subscriber, string $email): void
    {
        $ref = new ReflectionProperty($subscriber, 'email');
        $ref->setValue($subscriber, $email);
    }
}
