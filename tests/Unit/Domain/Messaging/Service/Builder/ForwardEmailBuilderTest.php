<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Builder;

use PhpList\Core\Domain\Common\PdfGenerator;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\LegacyUrlBuilder;
use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Exception\EmailBlacklistedException;
use PhpList\Core\Domain\Messaging\Exception\InvalidRecipientOrSubjectException;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Messaging\Service\AttachmentAdder;
use PhpList\Core\Domain\Messaging\Service\Builder\ForwardEmailBuilder;
use PhpList\Core\Domain\Messaging\Service\Builder\HttpReceivedStampBuilder;
use PhpList\Core\Domain\Messaging\Service\Constructor\CampaignMailContentBuilder;
use PhpList\Core\Domain\Messaging\Service\TemplateImageEmbedder;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Repository\UserBlacklistRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

class ForwardEmailBuilderTest extends TestCase
{
    private ConfigProvider&MockObject $configProvider;
    private EventLogManager&MockObject $eventLogManager;
    private UserBlacklistRepository&MockObject $blacklistRepository;
    private SubscriberHistoryManager&MockObject $subscriberHistoryManager;
    private SubscriberRepository&MockObject $subscriberRepository;
    private LoggerInterface&MockObject $logger;
    private CampaignMailContentBuilder&MockObject $mailConstructor;
    private TemplateImageEmbedder&MockObject $templateImageEmbedder;
    private LegacyUrlBuilder&MockObject $urlBuilder;
    private PdfGenerator&MockObject $pdfGenerator;
    private AttachmentAdder&MockObject $attachmentAdder;
    private TranslatorInterface&MockObject $translator;
    private HttpReceivedStampBuilder&MockObject $httpReceivedStampBuilder;

    protected function setUp(): void
    {
        $this->configProvider = $this->createMock(ConfigProvider::class);
        $this->eventLogManager = $this->createMock(EventLogManager::class);
        $this->blacklistRepository = $this->createMock(UserBlacklistRepository::class);
        $this->subscriberHistoryManager = $this->createMock(SubscriberHistoryManager::class);
        $this->subscriberRepository = $this->createMock(SubscriberRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->mailConstructor = $this->createMock(CampaignMailContentBuilder::class);
        $this->templateImageEmbedder = $this->getMockBuilder(TemplateImageEmbedder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__invoke'])
            ->getMock();
        $this->urlBuilder = $this->createMock(LegacyUrlBuilder::class);
        $this->pdfGenerator = $this->createMock(PdfGenerator::class);
        $this->attachmentAdder = $this->createMock(AttachmentAdder::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->httpReceivedStampBuilder = $this->createMock(HttpReceivedStampBuilder::class);

        // Defaults for config values used in headers
        $this->configProvider->method('getValue')->willReturnMap([
            [ConfigOption::PreferencesUrl, 'https://example.com/prefs'],
            [ConfigOption::UnsubscribeUrl, 'https://example.com/unsub'],
            [ConfigOption::SubscribeUrl, 'https://example.com/subscribe'],
            [ConfigOption::AdminAddress, 'admin@example.com'],
            [ConfigOption::AlwaysSendTextDomains, ''],
        ]);

        $this->urlBuilder->method('withUid')->willReturnCallback(
            static fn(string $url, ?string $uid): string => $url . (str_contains($url, '?') ? '&' : '?') . 'uid=' . $uid
        );
    }

    private function makeBuilder(
        string $googleSenderId = 'g-123',
        bool $useAmazonSes = false,
        bool $usePrecedenceHeader = true,
        bool $devVersion = true,
        ?string $devEmail = 'dev@example.com',
    ): ForwardEmailBuilder {
        return new ForwardEmailBuilder(
            configProvider: $this->configProvider,
            eventLogManager: $this->eventLogManager,
            blacklistRepository: $this->blacklistRepository,
            subscriberHistoryManager: $this->subscriberHistoryManager,
            subscriberRepository: $this->subscriberRepository,
            logger: $this->logger,
            mailContentBuilder: $this->mailConstructor,
            templateImageEmbedder: $this->templateImageEmbedder,
            urlBuilder: $this->urlBuilder,
            pdfGenerator: $this->pdfGenerator,
            attachmentAdder: $this->attachmentAdder,
            translator: $this->translator,
            httpReceivedStampBuilder: $this->httpReceivedStampBuilder,
            googleSenderId: $googleSenderId,
            useAmazonSes: $useAmazonSes,
            usePrecedenceHeader: $usePrecedenceHeader,
            devVersion: $devVersion,
            devEmail: $devEmail,
        );
    }

    public function testBuildsForwardEmailWithSubjectPrefixHeadersAndReplyTo(): void
    {
        $this->blacklistRepository->method('isEmailBlacklisted')->willReturn(false);

        $dto = new MessagePrecacheDto();
        // will be stripped of backslashes by stripslashes
        $dto->subject = 'Hello \\"World\\"';
        $dto->content = 'Body text';
        $dto->sendFormat = null;

        $friendEmail = 'friend@example.com';
        $fromEmail = 'from@example.com';
        $fromName = 'From Name';

        $this->translator->method('trans')->with('Fwd')->willReturn('Fwd');

        $this->mailConstructor
            ->expects(self::once())
            ->method('__invoke')
            ->willReturn(['<p>HTML</p>', 'TEXT']);

        $this->templateImageEmbedder
            ->expects(self::once())
            ->method('__invoke')
            ->with('<p>HTML</p>', 99)
            ->willReturn('<p>HTML</p>');

        $this->attachmentAdder
            ->expects(self::once())
            ->method('add')
            ->with($this->isInstanceOf(Email::class), 99, OutputFormat::Html, true)
            ->willReturn(true);

        $this->httpReceivedStampBuilder
            ->method('buildStamp')
            ->willReturn('from host [127.0.0.1] by example.org with HTTP; Wed, 01 Jan 2025 00:00:00 +0000');

        $builder = $this->makeBuilder(devVersion: true, devEmail: 'dev@example.com');
        [$email, $sentAs] = $builder->buildForwardEmail(
            messageId: 99,
            friendEmail: $friendEmail,
            forwardedBy: new Subscriber('alice@example.com'),
            data: $dto,
            htmlPref: true,
            fromName: $fromName,
            fromEmail: $fromEmail,
            forwardedPersonalNote: 'See this',
        );

        $this->assertSame(OutputFormat::TextAndHtml, $sentAs);

        // Subject prefixed and stripslashes applied
        $this->assertSame('Fwd: Hello "World"', $email->getSubject());

        // Reply-To set
        $this->assertSame($fromEmail, $email->getReplyTo()[0]->getAddress());
        $this->assertSame($fromName, $email->getReplyTo()[0]->getName());

        // Received header present
        $this->assertNotNull($email->getHeaders()->get('Received'));

        // Dev mode reroutes recipient
        $this->assertSame('dev@example.com', $email->getTo()[0]->getAddress());
    }

    public function testReturnsNullWhenEmptySubjectAndLogs(): void
    {
        $dto = new MessagePrecacheDto();
        $dto->subject = '';
        $friend = 'friend@example.com';

        $this->eventLogManager->expects(self::once())->method('log');

        $this->expectException(InvalidRecipientOrSubjectException::class);
        $this->expectExceptionMessage('Invalid recipient or subject.');

        $builder = $this->makeBuilder();
        $builder->buildForwardEmail(
            messageId: 1,
            friendEmail: $friend,
            forwardedBy: new Subscriber('alice@example.com'),
            data: $dto,
            htmlPref: false,
            fromName: 'X',
            fromEmail: 'x@example.com',
        );
    }

    public function testBlacklistReturnsNullAndMarksHistory(): void
    {
        $this->blacklistRepository->method('isEmailBlacklisted')->willReturn(true);

        $subscriber = $this->getMockBuilder(Subscriber::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setBlacklisted'])
            ->getMock();
        $subscriber->expects(self::once())->method('setBlacklisted')->with(true);

        $this->subscriberRepository->method('findOneByEmail')->with('friend@example.com')->willReturn($subscriber);
        $this->subscriberHistoryManager->expects(self::once())->method('addHistory');

        $dto = new MessagePrecacheDto();
        $dto->subject = 'S';
        $this->expectException(EmailBlacklistedException::class);
        $this->expectExceptionMessage('Email address is blacklisted.');

        $builder = $this->makeBuilder();
        $result = $builder->buildForwardEmail(
            messageId: 2,
            friendEmail: 'friend@example.com',
            forwardedBy: new Subscriber('alice@example.com'),
            data: $dto,
            htmlPref: false,
            fromName: 'From',
            fromEmail: 'from@example.com',
        );
    }
}
