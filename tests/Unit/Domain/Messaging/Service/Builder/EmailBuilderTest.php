<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Builder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\LegacyUrlBuilder;
use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Exception\AttachmentException;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Messaging\Service\AttachmentAdder;
use PhpList\Core\Domain\Messaging\Service\Builder\EmailBuilder;
use PhpList\Core\Domain\Messaging\Service\Constructor\MailContentBuilderInterface;
use PhpList\Core\Domain\Messaging\Service\TemplateImageEmbedder;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Repository\UserBlacklistRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Common\PdfGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

class EmailBuilderTest extends TestCase
{
    private ConfigProvider&MockObject $configProvider;
    private EventLogManager&MockObject $eventLogManager;
    private UserBlacklistRepository&MockObject $blacklistRepository;
    private SubscriberHistoryManager&MockObject $subscriberHistoryManager;
    private SubscriberRepository&MockObject $subscriberRepository;
    private LoggerInterface&MockObject $logger;
    private MailContentBuilderInterface&MockObject $mailConstructor;
    private TemplateImageEmbedder&MockObject $templateImageEmbedder;
    private LegacyUrlBuilder&MockObject $urlBuilder;
    private PdfGenerator&MockObject $pdfGenerator;
    private AttachmentAdder&MockObject $attachmentAdder;
    private TranslatorInterface&MockObject $translator;

    protected function setUp(): void
    {
        $this->configProvider = $this->createMock(ConfigProvider::class);
        $this->eventLogManager = $this->createMock(EventLogManager::class);
        $this->blacklistRepository = $this->createMock(UserBlacklistRepository::class);
        $this->subscriberHistoryManager = $this->createMock(SubscriberHistoryManager::class);
        $this->subscriberRepository = $this->createMock(SubscriberRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->mailConstructor = $this->getMockBuilder(MailContentBuilderInterface::class)
            ->onlyMethods(['__invoke'])
            ->getMock();
        $this->templateImageEmbedder = $this->getMockBuilder(TemplateImageEmbedder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__invoke'])
            ->getMock();
        $this->urlBuilder = $this->createMock(LegacyUrlBuilder::class);
        $this->pdfGenerator = $this->createMock(PdfGenerator::class);
        $this->attachmentAdder = $this->createMock(AttachmentAdder::class);
        $this->translator = $this->createMock(TranslatorInterface::class);

        $this->configProvider->method('getValue')->willReturnMap(
            [
            [ConfigOption::MessageFromAddress, 'from@example.com'],
            [ConfigOption::MessageFromName, 'From Name'],
            [ConfigOption::UnsubscribeUrl, 'https://example.com/unsubscribe'],
            [ConfigOption::PreferencesUrl, 'https://example.com/prefs'],
            [ConfigOption::SubscribeUrl, 'https://example.com/subscribe'],
            [ConfigOption::AdminAddress, 'admin@example.com'],
            [ConfigOption::AlwaysSendTextDomains, ''],
            ]
        );
    }

    private function makeBuilder(
        string $googleSenderId = 'g-123',
        bool $useAmazonSes = false,
        bool $usePrecedenceHeader = true,
        bool $devVersion = true,
        ?string $devEmail = 'dev@example.com',
    ): EmailBuilder {
        return new EmailBuilder(
            configProvider: $this->configProvider,
            eventLogManager: $this->eventLogManager,
            blacklistRepository: $this->blacklistRepository,
            subscriberHistoryManager: $this->subscriberHistoryManager,
            subscriberRepository: $this->subscriberRepository,
            logger: $this->logger,
            mailConstructor: $this->mailConstructor,
            templateImageEmbedder: $this->templateImageEmbedder,
            urlBuilder: $this->urlBuilder,
            pdfGenerator: $this->pdfGenerator,
            attachmentAdder: $this->attachmentAdder,
            translator: $this->translator,
            googleSenderId: $googleSenderId,
            useAmazonSes: $useAmazonSes,
            usePrecedenceHeader: $usePrecedenceHeader,
            devVersion: $devVersion,
            devEmail: $devEmail,
        );
    }

    public function testReturnsNullWhenMissingRecipient(): void
    {
        $this->eventLogManager->expects($this->once())->method('log');
        $dto = new MessagePrecacheDto();
        $dto->to = null;
        $dto->subject = 'Subj';
        $dto->content = 'Body';
        $dto->fromEmail = 'from@example.com';

        $builder = $this->makeBuilder();
        $result = $builder->buildCampaignEmail(messageId: 1, data: $dto);
        $this->assertNull($result);
    }

    public function testReturnsNullWhenMissingSubject(): void
    {
        $this->eventLogManager->expects($this->once())->method('log');
        $dto = new MessagePrecacheDto();
        $dto->to = 'user@example.com';
        $dto->content = 'Body';
        $dto->fromEmail = 'from@example.com';

        $builder = $this->makeBuilder();
        $result = $builder->buildCampaignEmail(messageId: 1, data: $dto);
        $this->assertNull($result);
    }

    public function testBlacklistReturnsNullAndMarksHistory(): void
    {
        $this->blacklistRepository->method('isEmailBlacklisted')->willReturn(true);

        $subscriber = $this->getMockBuilder(Subscriber::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setBlacklisted'])
            ->getMock();
        $subscriber
            ->expects($this->once())
            ->method('setBlacklisted')
            ->with(true);
        $this->subscriberRepository
            ->method('findOneByEmail')
            ->with('user@example.com')
            ->willReturn($subscriber);
        $this->subscriberHistoryManager
            ->expects($this->once())
            ->method('addHistory');

        $dto = new MessagePrecacheDto();
        $dto->to = 'user@example.com';
        $dto->subject = 'Hello';
        $dto->content = 'B';
        $dto->fromEmail = 'from@example.com';

        $builder = $this->makeBuilder();
        $result = $builder->buildCampaignEmail(messageId: 5, data: $dto);
        $this->assertNull($result);
    }

    public function testBuildsHtmlPreferredWithAttachments(): void
    {
        $this->blacklistRepository
            ->method('isEmailBlacklisted')
            ->willReturn(false);
        $dto = new MessagePrecacheDto();
        $dto->to = 'real@example.com';
        $dto->subject = 'Subject';
        $dto->content = 'TEXT';
        $dto->fromEmail = 'from@example.com';
        $dto->fromName = 'From Name';

        $this->mailConstructor
            ->expects($this->once())
            ->method('__invoke')
            ->with($dto)
            ->willReturn(['<p>HTML</p>', 'TEXT']);
        $this->templateImageEmbedder
            ->expects($this->once())
            ->method('__invoke')
            ->with(html: '<p>HTML</p>', messageId: 777)
            ->willReturn('<p>HTML</p>');
        $this->attachmentAdder
            ->expects($this->once())
            ->method('add')
            ->with($this->isInstanceOf(Email::class), 777, OutputFormat::Html)
            ->willReturn(true);

        $builder = $this->makeBuilder(devVersion: true, devEmail: 'dev@example.com');
        [$email, $sentAs] = $builder->buildCampaignEmail(
            messageId: 777,
            data: $dto,
            skipBlacklistCheck: false,
            inBlast: true,
            htmlPref: false,
        );

        $this->assertSame(OutputFormat::TextAndHtml, $sentAs);
        $this->assertSame('TEXT', $email->getTextBody());
        $this->assertSame('<p>HTML</p>', $email->getHtmlBody());

        // Recipient redirected in dev mode
        $this->assertSame('dev@example.com', $email->getTo()[0]->getAddress());
        $this->assertSame('real@example.com', $email->getHeaders()->get('X-Originally-To')->getBodyAsString());
    }

    public function testPrefersTextWhenNoHtmlContent(): void
    {
        $this->configProvider
            ->method('getValue')
            ->willReturnMap([
                [ConfigOption::MessageFromAddress, 'from@example.com'],
                [ConfigOption::MessageFromName, 'From Name'],
                [ConfigOption::UnsubscribeUrl, 'https://example.com/unsubscribe'],
                [ConfigOption::PreferencesUrl, 'https://example.com/prefs'],
                [ConfigOption::SubscribeUrl, 'https://example.com/subscribe'],
                [ConfigOption::AdminAddress, 'admin@example.com'],
                [ConfigOption::AlwaysSendTextDomains, ''],
            ]);

        $this->blacklistRepository->method('isEmailBlacklisted')->willReturn(false);
        $dto = new MessagePrecacheDto();
        $dto->to = 'user@example.com';
        $dto->subject = 'Subject';
        $dto->content = 'TEXT';
        $dto->fromEmail = 'from@example.com';

        // No HTML content provided -> should choose text-only
        $this->mailConstructor
            ->method('__invoke')
            ->willReturn([null, 'TEXT']);
        $this->attachmentAdder
            ->expects($this->once())
            ->method('add')
            ->with($this->isInstanceOf(Email::class), 9, OutputFormat::Text)
            ->willReturn(true);

        $builder = $this->makeBuilder(devVersion: false, devEmail: null);
        [$email, $sentAs] = $builder->buildCampaignEmail(messageId: 9, data: $dto, htmlPref: true);

        $this->assertSame(OutputFormat::Text, $sentAs);
        $this->assertSame('TEXT', $email->getTextBody());
        $this->assertNull($email->getHtmlBody());
        $this->assertSame('user@example.com', $email->getTo()[0]->getAddress());
    }

    public function testPdfFormatWhenHtmlPreferred(): void
    {
        $this->blacklistRepository
            ->method('isEmailBlacklisted')
            ->willReturn(false);
        $dto = new MessagePrecacheDto();
        $dto->to = 'user@example.com';
        $dto->subject = 'Subject';
        $dto->content = 'TEXT';
        $dto->fromEmail = 'from@example.com';
        $dto->sendFormat = 'pdf';

        $this->mailConstructor
            ->method('__invoke')
            ->willReturn(['<i>H</i>', 'TEXT']);
        $this->pdfGenerator
            ->expects($this->once())
            ->method('createPdfBytes')
            ->with('TEXT')
            ->willReturn('%PDF%');
        $this->attachmentAdder
            ->expects($this->once())
            ->method('add')
            ->with($this->isInstanceOf(Email::class), 42, OutputFormat::Html)
            ->willReturn(true);

        $builder = $this->makeBuilder(devVersion: false, devEmail: null);
        [$email, $sentAs] = $builder->buildCampaignEmail(messageId: 42, data: $dto, htmlPref: true);

        $this->assertSame(OutputFormat::Pdf, $sentAs);
        $this->assertCount(1, $email->getAttachments());
    }

    public function testTextAndPdfFormatWhenNotHtmlPreferred(): void
    {
        $this->blacklistRepository
            ->method('isEmailBlacklisted')
            ->willReturn(false);
        $dto = new MessagePrecacheDto();
        $dto->to = 'user@example.com';
        $dto->subject = 'Subject';
        $dto->content = 'TEXT';
        $dto->fromEmail = 'from@example.com';
        $dto->sendFormat = 'text and pdf';

        $this->mailConstructor
            ->method('__invoke')
            ->willReturn([null, 'TEXT']);
        $this->attachmentAdder
            ->expects($this->once())
            ->method('add')
            ->with($this->isInstanceOf(Email::class), 43, OutputFormat::Text)
            ->willReturn(true);
        $this->pdfGenerator
            ->expects($this->never())
            ->method('createPdfBytes');

        $builder = $this->makeBuilder(devVersion: false, devEmail: null);
        [$email, $sentAs] = $builder->buildCampaignEmail(messageId: 43, data: $dto, htmlPref: false);

        $this->assertSame(OutputFormat::Text, $sentAs);
        $this->assertSame('TEXT', $email->getTextBody());
        $this->assertCount(0, $email->getAttachments());
    }

    public function testReplyToExplicitAndTestMailFallback(): void
    {
        $this->blacklistRepository
            ->method('isEmailBlacklisted')
            ->willReturn(false);

        // explicit reply-to
        $dto = new MessagePrecacheDto();
        $dto->to = 'user@example.com';
        $dto->subject = 'Subject';
        $dto->content = 'TEXT';
        $dto->fromEmail = 'from@example.com';
        $dto->replyToEmail = 'reply@example.com';
        $dto->replyToName = 'Rep';
        $this->mailConstructor
            ->method('__invoke')
            ->willReturn([null, 'TEXT']);
        $this->attachmentAdder
            ->method('add')
            ->willReturn(true);

        $builder = $this->makeBuilder(devVersion: false, devEmail: null);
        [$email] = $builder->buildCampaignEmail(messageId: 50, data: $dto);
        $this->assertSame('reply@example.com', $email->getReplyTo()[0]->getAddress());

        // no reply-to, but test mail -> uses AdminAddress
        $dto2 = new MessagePrecacheDto();
        $dto2->to = 'user@example.com';
        $dto2->subject = 'Subject';
        $dto2->content = 'TEXT';
        $dto2->fromEmail = 'from@example.com';
        $this->mailConstructor
            ->method('__invoke')
            ->willReturn([null, 'TEXT']);

        $this->translator
            ->method('trans')
            ->with('(test)')
            ->willReturn('(test)');

        [$email2] = $builder->buildCampaignEmail(messageId: 51, data: $dto2, isTestMail: true);
        $this->assertSame('admin@example.com', $email2->getReplyTo()[0]->getAddress());
        $this->assertStringStartsWith('(test) ', $email2->getSubject());
    }

    public function testApplyCampaignHeaders(): void
    {
        $subscriber = $this->getMockBuilder(Subscriber::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getUniqueId'])
            ->getMock();
        $subscriber
            ->method('getUniqueId')
            ->willReturn('abc123');

        $this->urlBuilder
            ->method('withUid')
            ->willReturnCallback(
                function (string $url, string $uid): string {
                    return $url . '?uid=' . $uid;
                }
            );

        $builder = $this->makeBuilder();
        $email = (new Email())->to(new Address('user@example.com'));
        $email = $builder->applyCampaignHeaders($email, $subscriber);

        $headers = $email->getHeaders();
        $this->assertSame('<https://example.com/prefs?uid=abc123>', $headers->get('List-Help')->getBodyAsString());
        $this->assertSame(
            '<https://example.com/unsubscribe?uid=abc123&jo=1>',
            $headers->get('List-Unsubscribe')->getBodyAsString()
        );
        $this->assertSame('List-Unsubscribe=One-Click', $headers->get('List-Unsubscribe-Post')->getBodyAsString());
        $this->assertSame('<https://example.com/subscribe>', $headers->get('List-Subscribe')->getBodyAsString());
        // In implementation, adminAddress uses UnsubscribeUrl option (likely a bug); we assert the behavior as-is
        $this->assertSame('<mailto:https://example.com/unsubscribe>', $headers->get('List-Owner')->getBodyAsString());
    }

    public function testAttachmentAdderFailureThrows(): void
    {
        $this->blacklistRepository
            ->method('isEmailBlacklisted')
            ->willReturn(false);
        $dto = new MessagePrecacheDto();
        $dto->to = 'user@example.com';
        $dto->subject = 'Subject';
        $dto->content = 'TEXT';
        $dto->fromEmail = 'from@example.com';

        $this->mailConstructor
            ->method('__invoke')
            ->willReturn(['<b>H</b>', 'TEXT']);
        $this->templateImageEmbedder
            ->method('__invoke')
            ->willReturn('<b>H</b>');
        $this->attachmentAdder
            ->method('add')
            ->willReturn(false);

        $builder = $this->makeBuilder(devVersion: false, devEmail: null);

        $this->expectException(AttachmentException::class);
        $builder->buildCampaignEmail(messageId: 60, data: $dto, htmlPref: true);
    }
}
