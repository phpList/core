<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Exception\AttachmentCopyException;
use PhpList\Core\Domain\Messaging\Exception\MessageSizeLimitExceededException;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\MessageStatus;
use PhpList\Core\Domain\Messaging\Model\Message\UserMessageStatus;
use PhpList\Core\Domain\Messaging\Model\UserMessage;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Service\Builder\EmailBuilder;
use PhpList\Core\Domain\Messaging\Service\CampaignEmailSender;
use PhpList\Core\Domain\Messaging\Service\MailSizeChecker;
use PhpList\Core\Domain\Messaging\Service\MessageProcessingPreparator;
use PhpList\Core\Domain\Messaging\Service\MessageStatusUpdater;
use PhpList\Core\Domain\Messaging\Service\RateLimitedCampaignMailer;
use PhpList\Core\Domain\Messaging\Service\SystemNotificationMailer;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Mime\Email;

class CampaignEmailSenderTest extends TestCase
{
    private EmailBuilder|MockObject $campaignEmailBuilder;
    private RateLimitedCampaignMailer|MockObject $rateLimitedCampaignMailer;
    private MailSizeChecker|MockObject $mailSizeChecker;
    private MessageProcessingPreparator|MockObject $messagePreparator;
    private MessageRepository|MockObject $messageRepository;
    private MessageStatusUpdater|MockObject $messageStatusUpdater;
    private SystemNotificationMailer|MockObject $notificationMailer;
    private SubscriberHistoryManager|MockObject $subscriberHistoryManager;
    private EntityManagerInterface|MockObject $entityManager;
    private ConfigProvider|MockObject $configProvider;
    private TranslatorInterface|MockObject $translator;
    private LoggerInterface|MockObject $logger;
    private CampaignEmailSender $sender;
    private MessagePrecacheDto $precached;
    private Message|MockObject $campaign;
    private Subscriber|MockObject $subscriber;
    private UserMessage|MockObject $userMessage;

    protected function setUp(): void
    {
        $this->campaignEmailBuilder = $this->createMock(EmailBuilder::class);
        $this->rateLimitedCampaignMailer = $this->createMock(RateLimitedCampaignMailer::class);
        $this->mailSizeChecker = $this->createMock(MailSizeChecker::class);
        $this->messagePreparator = $this->createMock(MessageProcessingPreparator::class);
        $this->messageRepository = $this->createMock(MessageRepository::class);
        $this->messageStatusUpdater = $this->createMock(MessageStatusUpdater::class);
        $this->notificationMailer = $this->createMock(SystemNotificationMailer::class);
        $this->subscriberHistoryManager = $this->createMock(SubscriberHistoryManager::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->configProvider = $this->createMock(ConfigProvider::class);
        $this->translator = $this->createMock(Translator::class);
        $this->translator->method('trans')->willReturnCallback(fn (string $msg) => $msg);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->sender = new CampaignEmailSender(
            $this->campaignEmailBuilder,
            $this->rateLimitedCampaignMailer,
            $this->mailSizeChecker,
            $this->messagePreparator,
            $this->messageRepository,
            $this->messageStatusUpdater,
            $this->notificationMailer,
            $this->subscriberHistoryManager,
            $this->entityManager,
            $this->configProvider,
            $this->translator,
            $this->logger,
        );

        $this->precached = new MessagePrecacheDto();
        $this->campaign = $this->createMock(Message::class);
        $this->campaign->method('getId')->willReturn(123);

        $this->subscriber = $this->createMock(Subscriber::class);
        $this->subscriber->method('getId')->willReturn(1);
        $this->subscriber->method('getEmail')->willReturn('test@example.com');

        $this->userMessage = $this->createMock(UserMessage::class);

        $this->messagePreparator->method('processMessageLinks')->willReturn($this->precached);
    }

    public function testSendMarksSentAndIncrementsCountsOnSuccess(): void
    {
        $email = (new Email())->to('test@example.com')->subject('s')->text('x');

        $this->campaignEmailBuilder->method('buildCampaignEmail')->willReturn([$email, OutputFormat::Text]);

        $this->rateLimitedCampaignMailer->expects($this->once())->method('send')->with($email);
        $this->messageRepository->expects($this->once())
            ->method('incrementSentCounts')
            ->with(123, OutputFormat::Text);

        $this->userMessage->expects($this->once())->method('setStatus')->with(UserMessageStatus::Sent);

        $this->sender->send($this->campaign, $this->subscriber, $this->userMessage, $this->precached);
    }

    public function testSendMarksExcludedWhenBuilderReturnsNullAndSubscriberBlacklisted(): void
    {
        $this->subscriber->method('isBlacklisted')->willReturn(true);
        $this->campaignEmailBuilder->method('buildCampaignEmail')->willReturn(null);

        $this->userMessage->expects($this->once())->method('setStatus')->with(UserMessageStatus::Excluded);
        $this->rateLimitedCampaignMailer->expects($this->never())->method('send');

        $this->sender->send($this->campaign, $this->subscriber, $this->userMessage, $this->precached);
    }

    public function testSendMarksNotSentWhenBuilderReturnsNullAndSubscriberNotBlacklisted(): void
    {
        $this->subscriber->method('isBlacklisted')->willReturn(false);
        $this->campaignEmailBuilder->method('buildCampaignEmail')->willReturn(null);

        $this->userMessage->expects($this->once())->method('setStatus')->with(UserMessageStatus::NotSent);

        $this->sender->send($this->campaign, $this->subscriber, $this->userMessage, $this->precached);
    }

    public function testSendSuspendsCampaignAndRethrowsOnSizeLimitExceeded(): void
    {
        $email = (new Email())->to('test@example.com')->subject('s')->text('x');
        $this->campaignEmailBuilder->method('buildCampaignEmail')->willReturn([$email, OutputFormat::Text]);

        $exception = new MessageSizeLimitExceededException(2000000, 1000000);
        $this->rateLimitedCampaignMailer->method('send')->willThrowException($exception);

        $this->messageStatusUpdater->expects($this->once())
            ->method('update')
            ->with($this->campaign, MessageStatus::Suspended);

        $this->userMessage->expects($this->once())->method('setStatus')->with(UserMessageStatus::Sent);

        $this->expectException(MessageSizeLimitExceededException::class);

        $this->sender->send($this->campaign, $this->subscriber, $this->userMessage, $this->precached);
    }

    public function testSendSuspendsCampaignNotifiesAdminsAndRethrowsOnAttachmentCopyFailure(): void
    {
        $email = (new Email())->to('test@example.com')->subject('s')->text('x');
        $this->campaignEmailBuilder->method('buildCampaignEmail')->willReturn([$email, OutputFormat::Text]);

        $exception = new AttachmentCopyException('copy failed');
        $this->rateLimitedCampaignMailer->method('send')->willThrowException($exception);

        $this->configProvider->method('getValue')->willReturn('report@example.com');

        $this->messageStatusUpdater->expects($this->once())
            ->method('update')
            ->with($this->campaign, MessageStatus::Suspended);

        $this->userMessage->expects($this->once())->method('setStatus')->with(UserMessageStatus::NotSent);

        $this->notificationMailer->expects($this->once())
            ->method('send')
            ->with(123, 'report@example.com', 'phplist system error', 'copy failed');

        $this->expectException(AttachmentCopyException::class);

        $this->sender->send($this->campaign, $this->subscriber, $this->userMessage, $this->precached);
    }

    public function testSendMarksNotSentAndLogsOnGenericFailureWithoutRethrowing(): void
    {
        $email = (new Email())->to('test@example.com')->subject('s')->text('x');
        $this->campaignEmailBuilder->method('buildCampaignEmail')->willReturn([$email, OutputFormat::Text]);

        $exception = new Exception('boom');
        $this->rateLimitedCampaignMailer->method('send')->willThrowException($exception);

        $this->userMessage->expects($this->once())->method('setStatus')->with(UserMessageStatus::NotSent);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('boom', ['subscriber_id' => 1, 'campaign_id' => 123]);

        $this->sender->send($this->campaign, $this->subscriber, $this->userMessage, $this->precached);
    }

    public function testHandleInvalidEmailMarksStatusUnconfirmsAndRecordsHistory(): void
    {
        $this->subscriber->method('isConfirmed')->willReturn(true);

        $this->userMessage->expects($this->once())
            ->method('setStatus')
            ->with(UserMessageStatus::InvalidEmailAddress);

        $this->subscriber->expects($this->once())->method('setConfirmed')->with(false);
        $this->subscriberHistoryManager->expects($this->once())->method('addHistory');

        $this->sender->handleInvalidEmail($this->userMessage, $this->subscriber, $this->campaign);
    }
}
