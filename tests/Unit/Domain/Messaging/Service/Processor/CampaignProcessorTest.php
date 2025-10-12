<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Processor;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\MessageContent;
use PhpList\Core\Domain\Messaging\Model\Message\MessageMetadata;
use PhpList\Core\Domain\Messaging\Repository\UserMessageRepository;
use PhpList\Core\Domain\Messaging\Service\Handler\RequeueHandler;
use PhpList\Core\Domain\Messaging\Service\MaxProcessTimeLimiter;
use PhpList\Core\Domain\Messaging\Service\MessageProcessingPreparator;
use PhpList\Core\Domain\Messaging\Service\Processor\CampaignProcessor;
use PhpList\Core\Domain\Messaging\Service\RateLimitedCampaignMailer;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\Provider\SubscriberProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Translation\Translator;

class CampaignProcessorTest extends TestCase
{
    private RateLimitedCampaignMailer|MockObject $mailer;
    private EntityManagerInterface|MockObject $entityManager;
    private SubscriberProvider|MockObject $subscriberProvider;
    private MessageProcessingPreparator|MockObject $messagePreparator;
    private LoggerInterface|MockObject $logger;
    private OutputInterface|MockObject $output;
    private CampaignProcessor $campaignProcessor;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(RateLimitedCampaignMailer::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->subscriberProvider = $this->createMock(SubscriberProvider::class);
        $this->messagePreparator = $this->createMock(MessageProcessingPreparator::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->output = $this->createMock(OutputInterface::class);
        $userMessageRepository = $this->createMock(UserMessageRepository::class);

        $this->campaignProcessor = new CampaignProcessor(
            mailer: $this->mailer,
            entityManager: $this->entityManager,
            subscriberProvider: $this->subscriberProvider,
            messagePreparator: $this->messagePreparator,
            logger: $this->logger,
            userMessageRepository: $userMessageRepository,
            timeLimiter: $this->createMock(MaxProcessTimeLimiter::class),
            requeueHandler: $this->createMock(RequeueHandler::class),
            translator: new Translator('en'),
            subscriberHistoryManager: $this->createMock(SubscriberHistoryManager::class),
        );
    }

    public function testProcessWithNoSubscribers(): void
    {
        $campaign = $this->createCampaignMock();
        $metadata = $this->createMock(MessageMetadata::class);
        $campaign->method('getMetadata')->willReturn($metadata);

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

        $this->campaignProcessor->process($campaign, $this->output);
    }

    public function testProcessWithInvalidSubscriberEmail(): void
    {
        $campaign = $this->createCampaignMock();
        $metadata = $this->createMock(MessageMetadata::class);
        $campaign->method('getMetadata')->willReturn($metadata);

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

        $this->campaignProcessor->process($campaign, $this->output);
    }

    public function testProcessWithValidSubscriberEmail(): void
    {
        $campaign = $this->createCampaignMock();
        $metadata = $this->createMock(MessageMetadata::class);
        $campaign->method('getMetadata')->willReturn($metadata);

        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->method('getEmail')->willReturn('test@example.com');
        $subscriber->method('getId')->willReturn(1);

        $this->subscriberProvider->expects($this->once())
            ->method('getSubscribersForMessage')
            ->with($campaign)
            ->willReturn([$subscriber]);

        $this->messagePreparator->expects($this->once())
            ->method('processMessageLinks')
            ->with($campaign, 1)
            ->willReturn($campaign);

        $this->mailer->expects($this->once())
            ->method('composeEmail')
            ->with($campaign, $subscriber)
            ->willReturnCallback(function ($processed, $sub) use ($campaign, $subscriber) {
                $this->assertSame($campaign, $processed);
                $this->assertSame($subscriber, $sub);
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

        $this->campaignProcessor->process($campaign, $this->output);
    }

    public function testProcessWithMailerException(): void
    {
        $campaign = $this->createCampaignMock();
        $metadata = $this->createMock(MessageMetadata::class);
        $campaign->method('getMetadata')->willReturn($metadata);
        $campaign->method('getId')->willReturn(123);

        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->method('getEmail')->willReturn('test@example.com');
        $subscriber->method('getId')->willReturn(1);

        $this->subscriberProvider->expects($this->once())
            ->method('getSubscribersForMessage')
            ->with($campaign)
            ->willReturn([$subscriber]);

        $this->messagePreparator->expects($this->once())
            ->method('processMessageLinks')
            ->with($campaign, 1)
            ->willReturn($campaign);

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

        $this->output->expects($this->once())
            ->method('writeln')
            ->with('Failed to send to: test@example.com');

        $metadata->expects($this->atLeastOnce())
            ->method('setStatus');

        $this->entityManager->expects($this->atLeastOnce())
            ->method('flush');

        $this->campaignProcessor->process($campaign, $this->output);
    }

    public function testProcessWithMultipleSubscribers(): void
    {
        $campaign = $this->createCampaignMock();
        $metadata = $this->createMock(MessageMetadata::class);
        $campaign->method('getMetadata')->willReturn($metadata);

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
            ->willReturn($campaign);

        $this->mailer->expects($this->exactly(2))
            ->method('send');

        $metadata->expects($this->atLeastOnce())
            ->method('setStatus');

        $this->entityManager->expects($this->atLeastOnce())
            ->method('flush');

        $this->campaignProcessor->process($campaign, $this->output);
    }

    public function testProcessWithNullOutput(): void
    {
        $campaign = $this->createCampaignMock();
        $metadata = $this->createMock(MessageMetadata::class);
        $campaign->method('getMetadata')->willReturn($metadata);
        $campaign->method('getId')->willReturn(123);

        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->method('getEmail')->willReturn('test@example.com');
        $subscriber->method('getId')->willReturn(1);

        $this->subscriberProvider->expects($this->once())
            ->method('getSubscribersForMessage')
            ->with($campaign)
            ->willReturn([$subscriber]);

        $this->messagePreparator->expects($this->once())
            ->method('processMessageLinks')
            ->with($campaign, 1)
            ->willReturn($campaign);

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

        $this->campaignProcessor->process($campaign, null);
    }

    /**
     * Creates a mock for the Message class with content
     */
    private function createCampaignMock(): Message|MockObject
    {
        $campaign = $this->createMock(Message::class);
        $content = $this->createMock(MessageContent::class);
        
        $content->method('getSubject')->willReturn('Test Subject');
        $content->method('getTextMessage')->willReturn('Test text message');
        $content->method('getText')->willReturn('<p>Test HTML message</p>');
        
        $campaign->method('getContent')->willReturn($content);
        
        return $campaign;
    }
}
