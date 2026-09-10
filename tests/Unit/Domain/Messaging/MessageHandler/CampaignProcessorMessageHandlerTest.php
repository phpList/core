<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\MessageHandler;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Messaging\Message\CampaignProcessor\CampaignProcessorMessage;
use PhpList\Core\Domain\Messaging\MessageHandler\CampaignProcessorMessageHandler;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\MessageMetadata;
use PhpList\Core\Domain\Messaging\Model\Message\MessageStatus;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Service\CampaignAdminNotifier;
use PhpList\Core\Domain\Messaging\Service\CampaignExclusionService;
use PhpList\Core\Domain\Messaging\Service\CampaignSendingLoop;
use PhpList\Core\Domain\Messaging\Service\Handler\RequeueHandler;
use PhpList\Core\Domain\Messaging\Service\MessageDataLoader;
use PhpList\Core\Domain\Messaging\Service\MessagePrecacheService;
use PhpList\Core\Domain\Messaging\Service\MessageStatusUpdater;
use PhpList\Core\Domain\Subscription\Service\Provider\SubscriberProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

class CampaignProcessorMessageHandlerTest extends TestCase
{
    private MessageRepository|MockObject $messageRepository;
    private MessageDataLoader|MockObject $messageDataLoader;
    private MessagePrecacheService|MockObject $precacheService;
    private MessageStatusUpdater|MockObject $messageStatusUpdater;
    private CampaignAdminNotifier|MockObject $adminNotifier;
    private CampaignExclusionService|MockObject $exclusionService;
    private SubscriberProvider|MockObject $subscriberProvider;
    private CampaignSendingLoop|MockObject $sendingLoop;
    private RequeueHandler|MockObject $requeueHandler;
    private EntityManagerInterface|MockObject $entityManager;
    private LoggerInterface|MockObject $logger;
    private TranslatorInterface|MockObject $translator;
    private CampaignProcessorMessageHandler $handler;

    protected function setUp(): void
    {
        $this->messageRepository = $this->createMock(MessageRepository::class);
        $this->messageDataLoader = $this->createMock(MessageDataLoader::class);
        $this->precacheService = $this->createMock(MessagePrecacheService::class);
        $this->messageStatusUpdater = $this->createMock(MessageStatusUpdater::class);
        $this->adminNotifier = $this->createMock(CampaignAdminNotifier::class);
        $this->exclusionService = $this->createMock(CampaignExclusionService::class);
        $this->subscriberProvider = $this->createMock(SubscriberProvider::class);
        $this->sendingLoop = $this->createMock(CampaignSendingLoop::class);
        $this->requeueHandler = $this->createMock(RequeueHandler::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->translator = $this->createMock(Translator::class);
        $this->translator->method('trans')->willReturnCallback(fn (string $msg) => $msg);

        $this->handler = $this->createHandler();
    }

    private function createHandler(int $stuckCampaignThresholdSeconds = 0): CampaignProcessorMessageHandler
    {
        return new CampaignProcessorMessageHandler(
            messageRepository: $this->messageRepository,
            messageDataLoader: $this->messageDataLoader,
            precacheService: $this->precacheService,
            messageStatusUpdater: $this->messageStatusUpdater,
            adminNotifier: $this->adminNotifier,
            exclusionService: $this->exclusionService,
            subscriberProvider: $this->subscriberProvider,
            sendingLoop: $this->sendingLoop,
            requeueHandler: $this->requeueHandler,
            entityManager: $this->entityManager,
            logger: $this->logger,
            translator: $this->translator,
            stuckCampaignThresholdSeconds: $stuckCampaignThresholdSeconds,
        );
    }

    public function testInvokeWhenCampaignNotFound(): void
    {
        $message = new CampaignProcessorMessage(999);

        $this->messageRepository->expects($this->once())
            ->method('tryClaimForProcessing')
            ->with(999, 0)
            ->willReturn(null);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Campaign not found or not in submitted status', ['campaign_id' => 999]);

        $this->precacheService->expects($this->never())->method('precacheMessage');

        ($this->handler)($message);
    }

    public function testInvokePassesStuckCampaignThresholdToTryClaimForProcessing(): void
    {
        $handler = $this->createHandler(stuckCampaignThresholdSeconds: 1800);

        $message = new CampaignProcessorMessage(999);

        $this->messageRepository->expects($this->once())
            ->method('tryClaimForProcessing')
            ->with(999, 1800)
            ->willReturn(null);

        $handler($message);
    }

    public function testInvokeSuspendsCampaignWhenPrecacheFails(): void
    {
        $campaign = $this->createCampaignMock();
        $data = new CampaignProcessorMessage(1);

        $this->messageRepository->method('tryClaimForProcessing')->willReturn($campaign);
        $this->messageDataLoader->method('__invoke')->willReturn([]);
        $this->precacheService->expects($this->once())
            ->method('precacheMessage')
            ->willReturn(false);

        $this->messageStatusUpdater->expects($this->once())
            ->method('update')
            ->with($campaign, MessageStatus::Suspended);

        $this->adminNotifier->expects($this->never())->method('notifyStart');
        $this->sendingLoop->expects($this->never())->method('run');

        ($this->handler)($data);
    }

    public function testInvokeRunsFullPipelineAndMarksCampaignSent(): void
    {
        $campaign = $this->createCampaignMock();
        $data = new CampaignProcessorMessage(1);
        $loadedMessageData = ['subject' => 'hello'];

        $this->messageRepository->method('tryClaimForProcessing')->with(1, 0)->willReturn($campaign);
        $this->messageDataLoader->method('__invoke')->with($campaign)->willReturn($loadedMessageData);
        $this->precacheService->expects($this->once())
            ->method('precacheMessage')
            ->with($campaign, $loadedMessageData, false)
            ->willReturn(true);

        $this->adminNotifier->expects($this->once())
            ->method('notifyStart')
            ->with($campaign, $loadedMessageData, 1);

        $this->exclusionService->expects($this->once())
            ->method('resolveExcludeListIds')
            ->with($loadedMessageData)
            ->willReturn([5]);
        $this->exclusionService->expects($this->once())
            ->method('markExcludedSubscribers')
            ->with($campaign, $data, [5]);

        $subscribers = ['a subscriber'];
        $this->subscriberProvider->expects($this->once())
            ->method('getSubscribersForMessageOrLists')
            ->with($data, $campaign, [5])
            ->willReturn($subscribers);

        $this->sendingLoop->expects($this->once())
            ->method('run')
            ->with($campaign, $subscribers, $this->stringContains((string) $campaign->getId()))
            ->willReturn(false);

        $this->requeueHandler->expects($this->never())->method('handle');

        $this->messageStatusUpdater->expects($this->exactly(2))
            ->method('update')
            ->willReturnCallback(function (Message $m, MessageStatus $status) use ($campaign) {
                $this->assertSame($campaign, $m);
                static $calls = 0;
                $calls++;
                $expected = $calls === 1 ? MessageStatus::InProcess : MessageStatus::Sent;
                $this->assertSame($expected, $status);
            });

        ($this->handler)($data);
    }

    public function testInvokeRequeuesAndSkipsSentStatusWhenStoppedEarlyAndRequeued(): void
    {
        $campaign = $this->createCampaignMock();
        $data = new CampaignProcessorMessage(1);

        $this->messageRepository->method('tryClaimForProcessing')->willReturn($campaign);
        $this->messageDataLoader->method('__invoke')->willReturn([]);
        $this->precacheService->method('precacheMessage')->willReturn(true);
        $this->exclusionService->method('resolveExcludeListIds')->willReturn([]);
        $this->subscriberProvider->method('getSubscribersForMessageOrLists')->willReturn([]);

        $this->sendingLoop->expects($this->once())
            ->method('run')
            ->willReturn(true);

        $this->requeueHandler->expects($this->once())
            ->method('handle')
            ->with($campaign)
            ->willReturn(true);

        $this->entityManager->expects($this->once())->method('flush');

        $this->messageStatusUpdater->expects($this->once())
            ->method('update')
            ->with($campaign, MessageStatus::InProcess);

        ($this->handler)($data);
    }

    public function testInvokeMarksSentWhenStoppedEarlyButRequeueDeclines(): void
    {
        $campaign = $this->createCampaignMock();
        $data = new CampaignProcessorMessage(1);

        $this->messageRepository->method('tryClaimForProcessing')->willReturn($campaign);
        $this->messageDataLoader->method('__invoke')->willReturn([]);
        $this->precacheService->method('precacheMessage')->willReturn(true);
        $this->exclusionService->method('resolveExcludeListIds')->willReturn([]);
        $this->subscriberProvider->method('getSubscribersForMessageOrLists')->willReturn([]);

        $this->sendingLoop->expects($this->once())
            ->method('run')
            ->willReturn(true);

        $this->requeueHandler->expects($this->once())
            ->method('handle')
            ->with($campaign)
            ->willReturn(false);

        $this->messageStatusUpdater->expects($this->exactly(2))
            ->method('update')
            ->willReturnCallback(function (Message $m, MessageStatus $status) {
                static $calls = 0;
                $calls++;
                $expected = $calls === 1 ? MessageStatus::InProcess : MessageStatus::Sent;
                $this->assertSame($expected, $status);
            });

        ($this->handler)($data);
    }

    private function createCampaignMock(): Message|MockObject
    {
        $campaign = $this->createMock(Message::class);
        $campaign->method('getId')->willReturn(1);
        $metadata = $this->createMock(MessageMetadata::class);
        $campaign->method('getMetadata')->willReturn($metadata);

        return $campaign;
    }
}
