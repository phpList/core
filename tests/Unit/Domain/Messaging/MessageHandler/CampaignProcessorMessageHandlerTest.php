<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\MessageHandler;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Message\CampaignProcessorMessage;
use PhpList\Core\Domain\Messaging\MessageHandler\CampaignProcessorMessageHandler;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\MessageContent;
use PhpList\Core\Domain\Messaging\Model\Message\MessageMetadata;
use PhpList\Core\Domain\Messaging\Model\Message\MessageStatus;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageRepository;
use PhpList\Core\Domain\Messaging\Service\Builder\EmailBuilder;
use PhpList\Core\Domain\Messaging\Service\Builder\SystemEmailBuilder;
use PhpList\Core\Domain\Messaging\Service\Handler\RequeueHandler;
use PhpList\Core\Domain\Messaging\Service\MailSizeChecker;
use PhpList\Core\Domain\Messaging\Service\MaxProcessTimeLimiter;
use PhpList\Core\Domain\Messaging\Service\MessageDataLoader;
use PhpList\Core\Domain\Messaging\Service\MessageProcessingPreparator;
use PhpList\Core\Domain\Messaging\Service\MessagePrecacheService;
use PhpList\Core\Domain\Messaging\Service\RateLimitedCampaignMailer;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\Provider\SubscriberProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use ReflectionClass;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

class CampaignProcessorMessageHandlerTest extends TestCase
{
    private RateLimitedCampaignMailer|MockObject $mailer;
    private EntityManagerInterface|MockObject $entityManager;
    private SubscriberProvider|MockObject $subscriberProvider;
    private MessageProcessingPreparator|MockObject $messagePreparator;
    private LoggerInterface|MockObject $logger;
    private CampaignProcessorMessageHandler $handler;
    private MessageRepository|MockObject $messageRepository;
    private TranslatorInterface|MockObject $translator;
    private MessagePrecacheService|MockObject $precacheService;
    private CacheInterface|MockObject $cache;
    private MailerInterface|MockObject $symfonyMailer;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(RateLimitedCampaignMailer::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->subscriberProvider = $this->createMock(SubscriberProvider::class);
        $this->messagePreparator = $this->createMock(MessageProcessingPreparator::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->messageRepository = $this->createMock(MessageRepository::class);
        $userMessageRepository = $this->createMock(UserMessageRepository::class);
        $timeLimiter = $this->createMock(MaxProcessTimeLimiter::class);
        $requeueHandler = $this->createMock(RequeueHandler::class);
        $this->translator = $this->createMock(Translator::class);
        $this->precacheService = $this->createMock(MessagePrecacheService::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->symfonyMailer = $this->createMock(MailerInterface::class);

        $timeLimiter->method('start');
        $timeLimiter->method('shouldStop')->willReturn(false);

        $this->handler = new CampaignProcessorMessageHandler(
            mailer: $this->symfonyMailer,
            rateLimitedCampaignMailer: $this->mailer,
            entityManager: $this->entityManager,
            subscriberProvider: $this->subscriberProvider,
            messagePreparator: $this->messagePreparator,
            logger: $this->logger,
            cache: $this->cache,
            userMessageRepository: $userMessageRepository,
            timeLimiter: $timeLimiter,
            requeueHandler: $requeueHandler,
            translator: $this->translator,
            subscriberHistoryManager: $this->createMock(SubscriberHistoryManager::class),
            messageRepository: $this->messageRepository,
            precacheService: $this->precacheService,
            messageDataLoader: $this->createMock(MessageDataLoader::class),
            systemEmailBuilder: $this->createMock(SystemEmailBuilder::class),
            campaignEmailBuilder: $this->createMock(EmailBuilder::class),
            mailSizeChecker: $this->createMock(MailSizeChecker::class),
            configProvider: $this->createMock(ConfigProvider::class),
            bounceEmail: 'bounce@email.com',
        );
    }

    public function testInvokeWhenCampaignNotFound(): void
    {
        $message = new CampaignProcessorMessage(999);

        $this->messageRepository->expects($this->once())
            ->method('findByIdAndStatus')
            ->with(999, MessageStatus::Submitted)
            ->willReturn(null);

        $this->translator->method('trans')->willReturnCallback(fn(string $msg) => $msg);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Campaign not found or not in submitted status', ['campaign_id' => 999]);

        ($this->handler)($message);
    }

    public function testInvokeWithNoSubscribers(): void
    {
        $campaign = $this->createCampaignMock();
        $metadata = $this->createMock(MessageMetadata::class);
        $campaign->method('getMetadata')->willReturn($metadata);
        $campaign->method('getId')->willReturn(1);

        $this->messageRepository->method('findByIdAndStatus')
            ->with(1, MessageStatus::Submitted)
            ->willReturn($campaign);

        $this->precacheService->expects($this->once())
            ->method('precacheMessage')
            ->with($campaign, $this->anything())
            ->willReturn(true);

        $this->subscriberProvider->expects($this->once())
            ->method('getSubscribersForMessage')
            ->with($campaign)
            ->willReturn([]);

        $metadata->expects($this->atLeastOnce())
            ->method('setStatus');

        $this->entityManager->expects($this->atLeastOnce())
            ->method('flush');

        $this->symfonyMailer->expects($this->never())
            ->method('send');

        ($this->handler)(new CampaignProcessorMessage(1));
    }

    public function testInvokeWithInvalidSubscriberEmail(): void
    {
        $campaign = $this->createCampaignMock();
        $metadata = $this->createMock(MessageMetadata::class);
        $campaign->method('getMetadata')->willReturn($metadata);
        $campaign->method('getId')->willReturn(1);

        $this->messageRepository->method('findByIdAndStatus')
            ->with(1, MessageStatus::Submitted)
            ->willReturn($campaign);

        $this->precacheService->expects($this->once())
            ->method('precacheMessage')
            ->with($campaign, $this->anything())
            ->willReturn(true);

        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->method('getEmail')->willReturn('invalid-email');
        $subscriber->method('getId')->willReturn(1);

        $this->subscriberProvider->expects($this->once())
            ->method('getSubscribersForMessage')
            ->with($campaign)
            ->willReturn([$subscriber]);

        $metadata->expects($this->atLeastOnce())
            ->method('setStatus');

        $this->entityManager->expects($this->atLeastOnce())
            ->method('flush');

        $this->messagePreparator->expects($this->never())
            ->method('processMessageLinks');

        $this->symfonyMailer->expects($this->never())
            ->method('send');

        ($this->handler)(new CampaignProcessorMessage(1));
    }

    public function testInvokeWithValidSubscriberEmail(): void
    {
        $campaign = $this->createMock(Message::class);
        $precached = new MessagePrecacheDto();
        $precached->subject = 'Test Subject';
        $precached->content = '<p>Test HTML message</p>';
        $precached->textContent = 'Test text message';
        $precached->footer = 'Test footer message';
        $campaign->method('getContent')->willReturn($this->createContentMock());
        $metadata = $this->createMock(MessageMetadata::class);
        $campaign->method('getMetadata')->willReturn($metadata);
        $campaign->method('getId')->willReturn(1);

        $this->messageRepository->method('findByIdAndStatus')
            ->with(1, MessageStatus::Submitted)
            ->willReturn($campaign);

        $this->precacheService->expects($this->once())
            ->method('precacheMessage')
            ->with($campaign, $this->anything())
            ->willReturn(true);

        $this->cache->method('get')->willReturn($precached);

        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->method('getEmail')->willReturn('test@example.com');
        $subscriber->method('getId')->willReturn(1);

        $this->subscriberProvider->expects($this->once())
            ->method('getSubscribersForMessage')
            ->with($campaign)
            ->willReturn([$subscriber]);

        $this->messagePreparator->expects($this->once())
            ->method('processMessageLinks')
            ->with(1, $precached, $subscriber)
            ->willReturn($precached);

        // campaign emails are built via campaignEmailBuilder and sent via RateLimitedCampaignMailer
        $campaignEmailBuilder = (new ReflectionClass($this->handler))
            ->getProperty("campaignEmailBuilder");
        /** @var EmailBuilder|MockObject $campaignBuilderMock */
        $campaignBuilderMock = $campaignEmailBuilder->getValue($this->handler);

        $campaignBuilderMock->expects($this->once())
            ->method('buildPhplistEmail')
            ->willReturn(
                (new Email())
                    ->from('news@example.com')
                    ->to('test@example.com')
                    ->subject('Test Subject')
                    ->text('Test text message')
                    ->html('<p>Test HTML message</p>')
            );

        $this->mailer->expects($this->any())->method('send');

        $metadata->expects($this->atLeastOnce())
            ->method('setStatus');

        $this->entityManager->expects($this->atLeastOnce())
            ->method('flush');

        ($this->handler)(new CampaignProcessorMessage(1));
    }

    public function testInvokeWithMailerException(): void
    {
        $campaign = $this->createMock(Message::class);
        $precached = new MessagePrecacheDto();
        $precached->subject = 'Test Subject';
        $precached->content = '<p>Test HTML message</p>';
        $precached->textContent = 'Test text message';
        $precached->footer = 'Test footer message';
        $metadata = $this->createMock(MessageMetadata::class);
        $campaign->method('getContent')->willReturn($this->createContentMock());
        $campaign->method('getMetadata')->willReturn($metadata);
        $campaign->method('getId')->willReturn(123);

        $this->messageRepository->method('findByIdAndStatus')
            ->with(123, MessageStatus::Submitted)
            ->willReturn($campaign);

        $this->precacheService->expects($this->once())
            ->method('precacheMessage')
            ->with($campaign, $this->anything())
            ->willReturn(true);

        $this->cache->method('get')->willReturn($precached);

        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->method('getEmail')->willReturn('test@example.com');
        $subscriber->method('getId')->willReturn(1);

        $this->subscriberProvider->expects($this->once())
            ->method('getSubscribersForMessage')
            ->with($campaign)
            ->willReturn([$subscriber]);

        $this->messagePreparator->expects($this->once())
            ->method('processMessageLinks')
            ->with(123, $precached, $subscriber)
            ->willReturn($precached);

        // Build email and throw on rate-limited sender
        $campaignEmailBuilder = (new ReflectionClass($this->handler))
            ->getProperty("campaignEmailBuilder");

        /** @var EmailBuilder|MockObject $campaignBuilderMock */
        $campaignBuilderMock = $campaignEmailBuilder->getValue($this->handler);
        $campaignBuilderMock->expects($this->once())
            ->method('buildPhplistEmail')
            ->willReturn((new Email())->to('test@example.com')->subject('Test Subject')->text('x'));

        $exception = new Exception('Test exception');
        $this->mailer->expects($this->once())
            ->method('send')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Test exception', [
                'subscriber_id' => 1,
                'campaign_id' => 123,
            ]);

        $metadata->expects($this->atLeastOnce())
            ->method('setStatus');

        $this->entityManager->expects($this->atLeastOnce())
            ->method('flush');

        ($this->handler)(new CampaignProcessorMessage(123));
    }

    public function testInvokeWithMultipleSubscribers(): void
    {
        $campaign = $this->createCampaignMock();
        $precached = new MessagePrecacheDto();
        $precached->subject = 'Test Subject';
        $precached->content = '<p>Test HTML message</p>';
        $precached->textContent = 'Test text message';
        $precached->footer = 'Test footer message';
        $metadata = $this->createMock(MessageMetadata::class);
        $campaign->method('getMetadata')->willReturn($metadata);
        $campaign->method('getId')->willReturn(1);

        $this->messageRepository->method('findByIdAndStatus')
            ->with(1, MessageStatus::Submitted)
            ->willReturn($campaign);

        $this->precacheService->expects($this->once())
            ->method('precacheMessage')
            ->with($campaign, $this->anything())
            ->willReturn(true);

        $this->cache->method('get')->willReturn($precached);

        $subscriber1 = $this->createMock(Subscriber::class);
        $subscriber1->method('getEmail')->willReturn('test1@example.com');
        $subscriber1->method('getId')->willReturn(1);

        $subscriber2 = $this->createMock(Subscriber::class);
        $subscriber2->method('getEmail')->willReturn('test2@example.com');
        $subscriber2->method('getId')->willReturn(2);

        $subscriber3 = $this->createMock(Subscriber::class);
        $subscriber3->method('getEmail')->willReturn('invalid-email');
        $subscriber3->method('getId')->willReturn(3);

        $this->subscriberProvider->expects($this->once())
            ->method('getSubscribersForMessage')
            ->with($campaign)
            ->willReturn([$subscriber1, $subscriber2, $subscriber3]);

        $this->messagePreparator->expects($this->exactly(2))
            ->method('processMessageLinks')
            ->withConsecutive(
                [1, $precached, $subscriber1],
                [1, $precached, $subscriber2]
            )
            ->willReturnOnConsecutiveCalls($precached, $precached);

        // Configure builder to return emails for first two subscribers
        $campaignEmailBuilder = (new ReflectionClass($this->handler))
            ->getProperty("campaignEmailBuilder");
        /** @var EmailBuilder|MockObject $campaignBuilderMock */
        $campaignBuilderMock = $campaignEmailBuilder->getValue($this->handler);
        $campaignBuilderMock->expects($this->exactly(2))
            ->method('buildPhplistEmail')
            ->willReturnOnConsecutiveCalls(
                (new Email())->to('test1@example.com')->subject('Test Subject')->text('x'),
                (new Email())->to('test2@example.com')->subject('Test Subject')->text('x')
            );

        $this->mailer->expects($this->exactly(2))
            ->method('send');

        $metadata->expects($this->atLeastOnce())
            ->method('setStatus');

        $this->entityManager->expects($this->atLeastOnce())
            ->method('flush');

        ($this->handler)(new CampaignProcessorMessage(1));
    }

    /**
     * Creates a mock for the Message class with content
     */
    private function createCampaignMock(): Message|MockObject
    {
        $campaign = $this->createMock(Message::class);
        $content = $this->createContentMock();
        $campaign->method('getContent')->willReturn($content);
        
        return $campaign;
    }

    private function createContentMock(): MessageContent|MockObject
    {
        $content = $this->createMock(MessageContent::class);

        $content->method('getSubject')->willReturn('Test Subject');
        $content->method('getTextMessage')->willReturn('Test text message');
        $content->method('getText')->willReturn('<p>Test HTML message</p>');

        return $content;
    }
}
