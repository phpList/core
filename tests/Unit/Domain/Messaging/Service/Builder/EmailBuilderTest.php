<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Builder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Service\Builder\EmailBuilder;
use PhpList\Core\Domain\Messaging\Service\Constructor\SystemMailContentBuilder;
use PhpList\Core\Domain\Messaging\Service\TemplateImageEmbedder;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Repository\UserBlacklistRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Address;

class EmailBuilderTest extends TestCase
{
    private ConfigProvider&MockObject $configProvider;
    private EventLogManager&MockObject $eventLogManager;
    private UserBlacklistRepository&MockObject $blacklistRepository;
    private SubscriberHistoryManager&MockObject $subscriberHistoryManager;
    private SubscriberRepository&MockObject $subscriberRepository;
    private SystemMailContentBuilder&MockObject $systemMailConstructor;
    private TemplateImageEmbedder&MockObject $templateImageEmbedder;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->configProvider = $this->createMock(ConfigProvider::class);
        $this->eventLogManager = $this->createMock(EventLogManager::class);
        $this->blacklistRepository = $this->createMock(UserBlacklistRepository::class);
        $this->subscriberHistoryManager = $this->createMock(SubscriberHistoryManager::class);
        $this->subscriberRepository = $this->createMock(SubscriberRepository::class);
        $this->systemMailConstructor = $this->getMockBuilder(SystemMailContentBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__invoke'])
            ->getMock();
        $this->templateImageEmbedder = $this->getMockBuilder(TemplateImageEmbedder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__invoke'])
            ->getMock();
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function createBuilder(
        string $googleSenderId = 'gsender',
        bool $useAmazonSes = false,
        bool $usePrecedenceHeader = true,
        bool $devVersion = true,
        ?string $devEmail = 'dev@example.com',
    ): EmailBuilder {
        // Default config values used by EmailBuilder
        $this->configProvider->method('getValue')->willReturnMap([
            [ConfigOption::MessageFromAddress, 'from@example.com'],
            [ConfigOption::MessageFromName, 'From Name'],
            [ConfigOption::UnsubscribeUrl, 'https://example.com/unsubscribe'],
            [ConfigOption::WordWrap, 0],
        ]);

        return new EmailBuilder(
            configProvider: $this->configProvider,
            eventLogManager: $this->eventLogManager,
            blacklistRepository: $this->blacklistRepository,
            subscriberHistoryManager: $this->subscriberHistoryManager,
            subscriberRepository: $this->subscriberRepository,
            mailConstructor: $this->systemMailConstructor,
            templateImageEmbedder: $this->templateImageEmbedder,
            logger: $this->logger,
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

        $builder = $this->createBuilder();
        $result = $builder->buildPhplistEmail(messageId: 123, to: null, subject: 'Subj', message: 'Body');
        $this->assertNull($result);
    }

    public function testReturnsNullWhenMissingSubject(): void
    {
        $this->eventLogManager->expects($this->once())->method('log');

        $builder = $this->createBuilder();
        $result = $builder->buildPhplistEmail(messageId: 123, to: 'user@example.com', subject: null, message: 'Body');
        $this->assertNull($result);
    }

    public function testReturnsNullWhenBlacklistedAndHistoryUpdated(): void
    {
        $this->blacklistRepository->method('isEmailBlacklisted')->willReturn(true);

        $subscriber = $this->getMockBuilder(Subscriber::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setBlacklisted'])
            ->getMock();
        $subscriber->expects($this->once())->method('setBlacklisted')->with(true);

        $this->subscriberRepository->method('findOneByEmail')->with('user@example.com')->willReturn($subscriber);
        $this->subscriberHistoryManager->expects($this->once())->method('addHistory');

        $builder = $this->createBuilder();
        $result = $builder->buildPhplistEmail(messageId: 55, to: 'user@example.com', subject: 'Hi', message: 'Body');
        $this->assertNull($result);
    }

    public function testBuildsEmailWithExpectedHeadersAndBodiesInDevMode(): void
    {
        $this->blacklistRepository->method('isEmailBlacklisted')->willReturn(false);

        // SystemMailConstructor returns both html and text bodies
        $this->systemMailConstructor->expects($this->once())
            ->method('__invoke')
            ->with('Body', 'Subject')
            ->willReturn(['<p>HTML</p>', 'TEXT']);

        // TemplateImageEmbedder invoked when HTML present
        $this->templateImageEmbedder->expects($this->once())
            ->method('__invoke')
            ->with(html: '<p>HTML</p>', messageId: 777)
            ->willReturn('<p>HTML</p>');

        $builder = $this->createBuilder(
            googleSenderId: 'g-123',
            useAmazonSes: false,
            usePrecedenceHeader: true,
            devVersion: true,
            devEmail: 'dev@example.com'
        );

        $email = $builder->buildPhplistEmail(
            messageId: 777,
            to: 'real@example.com',
            subject: 'Subject',
            message: 'Body',
            skipBlacklistCheck: false,
            inBlast: true
        );

        $this->assertNotNull($email);

        // Recipient is redirected to dev email in dev mode
        $this->assertCount(1, $email->getTo());
        $this->assertInstanceOf(Address::class, $email->getTo()[0]);
        $this->assertSame('dev@example.com', $email->getTo()[0]->getAddress());
        $this->assertSame('', $email->getTo()[0]->getName());

        // Basic headers
        $headers = $email->getHeaders();
        $this->assertTrue($headers->has('X-MessageID'));
        $this->assertSame('777', $headers->get('X-MessageID')->getBodyAsString());

        $this->assertTrue($headers->has('X-ListMember'));
        $this->assertSame('dev@example.com', $headers->get('X-ListMember')->getBodyAsString());

        $this->assertTrue($headers->has('Feedback-ID'));
        $this->assertSame('777:g-123', $headers->get('Feedback-ID')->getBodyAsString());

        // Precedence: bulk when not using Amazon SES and enabled
        $this->assertTrue($headers->has('Precedence'));
        $this->assertSame('bulk', $headers->get('Precedence')->getBodyAsString());

        // X-Blast for campaign blasts
        $this->assertTrue($headers->has('X-Blast'));
        $this->assertSame('1', $headers->get('X-Blast')->getBodyAsString());

        // List-Unsubscribe includes the email
        $this->assertTrue($headers->has('List-Unsubscribe'));
        $this->assertStringContainsString(
            'email=dev%40example.com',
            $headers->get('List-Unsubscribe')->getBodyAsString()
        );

        // In dev mode with redirected recipient, no "X-Originally to" header is set per current implementation
        $this->assertFalse($headers->has('X-Originally to'));

        // Bodies
        $this->assertSame('<p>HTML</p>', $email->getHtmlBody());
        $this->assertSame('TEXT', $email->getTextBody());
        $this->assertSame('Subject', $email->getSubject());
    }
}
