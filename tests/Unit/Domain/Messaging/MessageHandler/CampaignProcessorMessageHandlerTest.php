<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\MessageHandler;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Configuration\Service\UserPersonalizer;
use PhpList\Core\Domain\Messaging\Message\CampaignProcessorMessage;
use PhpList\Core\Domain\Messaging\MessageHandler\CampaignProcessorMessageHandler;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\MessageContent;
use PhpList\Core\Domain\Messaging\Model\Message\MessageMetadata;
use PhpList\Core\Domain\Messaging\Model\Message\MessageStatus;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageRepository;
use PhpList\Core\Domain\Messaging\Service\Handler\RequeueHandler;
use PhpList\Core\Domain\Messaging\Service\Manager\MessageDataManager;
use PhpList\Core\Domain\Messaging\Service\MaxProcessTimeLimiter;
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
        $userPersonalizer = $this->createMock(UserPersonalizer::class);

        $timeLimiter->method('start');
        $timeLimiter->method('shouldStop')->willReturn(false);

        // Ensure personalization returns original text so assertions on replaced links remain valid
        $userPersonalizer
            ->method('personalize')
            ->willReturnCallback(function (string $text) {
                return $text;
            });

        $this->handler = new CampaignProcessorMessageHandler(
            mailer: $this->mailer,
            entityManager: $this->entityManager,
            subscriberProvider: $this->subscriberProvider,
            messagePreparator: $this->messagePreparator,
            logger: $this->logger,
            cache: $this->createMock(CacheInterface::class),
            userMessageRepository: $userMessageRepository,
            timeLimiter: $timeLimiter,
            requeueHandler: $requeueHandler,
            translator: $this->translator,
            subscriberHistoryManager: $this->createMock(SubscriberHistoryManager::class),
            messageRepository: $this->messageRepository,
            eventLogManager: $this->createMock(EventLogManager::class),
            messageDataManager: $this->createMock(MessageDataManager::class),
            precacheService: $this->precacheService,
            userPersonalizer: $userPersonalizer,
            maxMailSize: 0,
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

        $this->subscriberProvider->expects($this->once())
            ->method('getSubscribersForMessage')
            ->with($campaign)
            ->willReturn([]);

        $metadata->expects($this->atLeastOnce())
            ->method('setStatus');

        $this->entityManager->expects($this->atLeastOnce())
            ->method('flush');

        $this->mailer->expects($this->never())
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

        $this->mailer->expects($this->never())
            ->method('send');

        ($this->handler)(new CampaignProcessorMessage(1));
    }

    public function testInvokeWithValidSubscriberEmail(): void
    {
        $campaign = $this->createMock(Message::class);
        $content = $this->createContentMock();
        $content->method('getText')->willReturn('<p>Test HTML message</p>');
        $content->method('getFooter')->willReturn('<p>Test footer message</p>');
        $campaign->method('getContent')->willReturn($content);
        $metadata = $this->createMock(MessageMetadata::class);
        $campaign->method('getMetadata')->willReturn($metadata);
        $campaign->method('getId')->willReturn(1);

        $this->messageRepository->method('findByIdAndStatus')
            ->with(1, MessageStatus::Submitted)
            ->willReturn($campaign);

        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->method('getEmail')->willReturn('test@example.com');
        $subscriber->method('getId')->willReturn(1);

        $this->subscriberProvider->expects($this->once())
            ->method('getSubscribersForMessage')
            ->with($campaign)
            ->willReturn([$subscriber]);

        $this->messagePreparator->expects($this->once())
            ->method('processMessageLinks')
            ->willReturn($content);

        $this->mailer->expects($this->once())
            ->method('composeEmail')
            ->with(
                $this->identicalTo($campaign),
                $this->identicalTo($subscriber),
                $this->identicalTo($content)
            )
            ->willReturnCallback(function ($camp, $sub, $proc) use ($campaign, $subscriber, $content) {
                $this->assertSame($campaign, $camp);
                $this->assertSame($subscriber, $sub);
                $this->assertSame($content, $proc);

                return (new Email())
                    ->from('news@example.com')
                    ->to('test@example.com')
                    ->subject('Test Subject')
                    ->text('Test text message')
                    ->html('<p>Test HTML message</p>');
            });

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf(Email::class));

        $metadata->expects($this->atLeastOnce())
            ->method('setStatus');

        $this->entityManager->expects($this->atLeastOnce())
            ->method('flush');

        ($this->handler)(new CampaignProcessorMessage(1));
    }

    public function testInvokeWithMailerException(): void
    {
        $campaign = $this->createMock(Message::class);
        $content = $this->createContentMock();
        $content->method('getText')->willReturn('<p>Test HTML message</p>');
        $content->method('getFooter')->willReturn('<p>Test footer message</p>');
        $metadata = $this->createMock(MessageMetadata::class);
        $campaign->method('getContent')->willReturn($content);
        $campaign->method('getMetadata')->willReturn($metadata);
        $campaign->method('getId')->willReturn(123);

        $this->messageRepository->method('findByIdAndStatus')
            ->with(123, MessageStatus::Submitted)
            ->willReturn($campaign);

        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->method('getEmail')->willReturn('test@example.com');
        $subscriber->method('getId')->willReturn(1);

        $this->precacheService->expects($this->once())
            ->method('getOrCacheBaseMessageContent')
            ->with($campaign)
            ->willReturn($content);

        $this->subscriberProvider->expects($this->once())
            ->method('getSubscribersForMessage')
            ->with($campaign)
            ->willReturn([$subscriber]);

        $this->messagePreparator->expects($this->once())
            ->method('processMessageLinks')
            ->with(123, $content, $subscriber)
            ->willReturn($content);

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
        $content = $this->createContentMock();
        $content->method('getText')->willReturn('<p>Test HTML message</p>');
        $content->method('getFooter')->willReturn('<p>Test footer message</p>');
        $metadata = $this->createMock(MessageMetadata::class);
        $campaign->method('getMetadata')->willReturn($metadata);
        $campaign->method('getId')->willReturn(1);

        $this->messageRepository->method('findByIdAndStatus')
            ->with(1, MessageStatus::Submitted)
            ->willReturn($campaign);

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
            ->willReturn($content);

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
