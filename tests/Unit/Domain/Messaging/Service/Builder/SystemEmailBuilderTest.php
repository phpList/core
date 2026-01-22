<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Builder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Messaging\Service\Builder\SystemEmailBuilder;
use PhpList\Core\Domain\Messaging\Service\Constructor\MailContentBuilderInterface;
use PhpList\Core\Domain\Messaging\Service\TemplateImageEmbedder;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Repository\UserBlacklistRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Address;

class SystemEmailBuilderTest extends TestCase
{
    private ConfigProvider&MockObject $configProvider;
    private EventLogManager&MockObject $eventLogManager;
    private UserBlacklistRepository&MockObject $blacklistRepository;
    private SubscriberHistoryManager&MockObject $subscriberHistoryManager;
    private SubscriberRepository&MockObject $subscriberRepository;
    private MailContentBuilderInterface&MockObject $mailConstructor;
    private TemplateImageEmbedder&MockObject $templateImageEmbedder;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->configProvider = $this->createMock(ConfigProvider::class);
        $this->eventLogManager = $this->createMock(EventLogManager::class);
        $this->blacklistRepository = $this->createMock(UserBlacklistRepository::class);
        $this->subscriberHistoryManager = $this->createMock(SubscriberHistoryManager::class);
        $this->subscriberRepository = $this->createMock(SubscriberRepository::class);
        $this->mailConstructor = $this->getMockBuilder(MailContentBuilderInterface::class)
            ->onlyMethods(['__invoke'])
            ->getMock();
        $this->templateImageEmbedder = $this->getMockBuilder(TemplateImageEmbedder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__invoke'])
            ->getMock();
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->configProvider->method('getValue')->willReturnMap(
            [
            [ConfigOption::MessageFromAddress, 'from@example.com'],
            [ConfigOption::MessageFromName, 'From Name'],
            [ConfigOption::UnsubscribeUrl, 'https://example.com/unsubscribe'],
            ]
        );
    }

    private function makeBuilder(
        string $googleSenderId = 'g-123',
        bool $useAmazonSes = false,
        bool $usePrecedenceHeader = true,
        bool $devVersion = true,
        ?string $devEmail = 'dev@example.com',
    ): SystemEmailBuilder {
        return new SystemEmailBuilder(
            configProvider: $this->configProvider,
            eventLogManager: $this->eventLogManager,
            blacklistRepository: $this->blacklistRepository,
            subscriberHistoryManager: $this->subscriberHistoryManager,
            subscriberRepository: $this->subscriberRepository,
            mailConstructor: $this->mailConstructor,
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
        $dto = new MessagePrecacheDto();
        $dto->to = null;
        $dto->subject = 'Subj';
        $dto->content = 'Body';

        $builder = $this->makeBuilder();
        $result = $builder->buildPhplistEmail(messageId: 1, data: $dto);
        $this->assertNull($result);
    }

    public function testReturnsNullWhenMissingSubject(): void
    {
        $this->eventLogManager->expects($this->once())->method('log');
        $dto = new MessagePrecacheDto();
        $dto->to = 'user@example.com';
        $dto->content = 'Body';

        $builder = $this->makeBuilder();
        $result = $builder->buildPhplistEmail(messageId: 1, data: $dto);
        $this->assertNull($result);
    }

    public function testReturnsNullWhenBlacklistedAndHistoryUpdated(): void
    {
        $this->blacklistRepository->method('isEmailBlacklisted')->willReturn(true);

        $subscriber = $this->getMockBuilder(Subscriber::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setBlacklisted'])
            ->getMock();
        $subscriber->expects($this->once())
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

        $builder = $this->makeBuilder();
        $result = $builder->buildPhplistEmail(messageId: 5, data: $dto);
        $this->assertNull($result);
    }

    public function testBuildsEmailWithExpectedHeadersAndBodiesInDevMode(): void
    {
        $this->blacklistRepository
            ->method('isEmailBlacklisted')
            ->willReturn(false);
        $dto = new MessagePrecacheDto();
        $dto->to = 'real@example.com';
        $dto->subject = 'Subject';
        $dto->content = 'TEXT';

        $this->mailConstructor->expects($this->once())
            ->method('__invoke')
            ->with($dto)
            ->willReturn(['<p>HTML</p>', 'TEXT']);

        $this->templateImageEmbedder->expects($this->once())
            ->method('__invoke')
            ->with(html: '<p>HTML</p>', messageId: 777)
            ->willReturn('<p>HTML</p>');

        $builder = $this->makeBuilder(
            googleSenderId: 'g-123',
            useAmazonSes: false,
            usePrecedenceHeader: true,
            devVersion: true,
            devEmail: 'dev@example.com'
        );

        $email = $builder->buildPhplistEmail(
            messageId: 777,
            data: $dto,
            skipBlacklistCheck: false,
            inBlast: true
        );

        $this->assertNotNull($email);

        // Recipient is redirected to dev email in dev mode
        $this->assertCount(1, $email->getTo());
        $this->assertInstanceOf(Address::class, $email->getTo()[0]);
        $this->assertSame('dev@example.com', $email->getTo()[0]->getAddress());

        // Headers
        $headers = $email->getHeaders();
        $this->assertSame('777', $headers->get('X-MessageID')->getBodyAsString());
        $this->assertSame('dev@example.com', $headers->get('X-ListMember')->getBodyAsString());
        $this->assertSame('777:g-123', $headers->get('Feedback-ID')->getBodyAsString());
        $this->assertSame('bulk', $headers->get('Precedence')->getBodyAsString());
        $this->assertSame('1', $headers->get('X-Blast')->getBodyAsString());

        $this->assertTrue($headers->has('X-Originally-To'));
        $this->assertSame('real@example.com', $headers->get('X-Originally-To')->getBodyAsString());

        $this->assertTrue($headers->has('List-Unsubscribe'));
        $this->assertStringContainsString(
            'email=dev%40example.com',
            $headers->get('List-Unsubscribe')->getBodyAsString()
        );

        // From and subject
        $this->assertSame('from@example.com', $email->getFrom()[0]->getAddress());
        $this->assertSame('From Name', $email->getFrom()[0]->getName());
        $this->assertSame('Subject', $email->getSubject());

        // Bodies
        $this->assertSame('TEXT', $email->getTextBody());
        $this->assertSame('<p>HTML</p>', $email->getHtmlBody());
    }
}
