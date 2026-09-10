<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\MessageHandler;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Messaging\Message\CampaignProcessor\CampaignProcessorMessage;
use PhpList\Core\Domain\Messaging\Message\CampaignProcessor\SyncCampaignProcessorMessage;
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
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
#[AsMessageHandler]
class CampaignProcessorMessageHandler
{
    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly MessageDataLoader $messageDataLoader,
        private readonly MessagePrecacheService $precacheService,
        private readonly MessageStatusUpdater $messageStatusUpdater,
        private readonly CampaignAdminNotifier $adminNotifier,
        private readonly CampaignExclusionService $exclusionService,
        private readonly SubscriberProvider $subscriberProvider,
        private readonly CampaignSendingLoop $sendingLoop,
        private readonly RequeueHandler $requeueHandler,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
        #[Autowire('%messaging.stuck_campaign_threshold%')] private readonly int $stuckCampaignThresholdSeconds = 0,
    ) {
    }

    public function __invoke(CampaignProcessorMessage|SyncCampaignProcessorMessage $data): void
    {
        // todo: recheck this stuckCampaignThresholdSeconds logic
        $campaign = $this->messageRepository->tryClaimForProcessing(
            $data->getMessageId(),
            $this->stuckCampaignThresholdSeconds
        );
        if (!$campaign) {
            $this->logger->warning(
                $this->translator->trans('Campaign not found or not in submitted status'),
                ['campaign_id' => $data->getMessageId()]
            );

            return;
        }

        $loadedMessageData = ($this->messageDataLoader)($campaign);
//        if (!empty($loadedMessageData['resetstats'])) {
//            resetMessageStatistics($loadedMessageData['id']);
//            setMessageData($loadedMessageData['id'], 'resetstats', 0);
//        }
//        $stopSending = false;
//        if (!empty($loadedMessageData['finishsending'])) {
//            $finishSendingBefore = mktime(
//                $loadedMessageData['finishsending']['hour'],
//                $loadedMessageData['finishsending']['minute'],
//                0,
//                $loadedMessageData['finishsending']['month'],
//                $loadedMessageData['finishsending']['day'],
//                $loadedMessageData['finishsending']['year'],
//            );
//            $secondsTogo = $finishSendingBefore - time();
//            $stopSending = $secondsTogo < 0;
//        }
//        $userSelection = $loadedMessageData['userselection'];

        $cacheKey = sprintf('messaging.message.base.%d.%d', $campaign->getId(), 0);
        if (!$this->precacheService->precacheMessage(
            campaign: $campaign,
            loadedMessageData: $loadedMessageData,
            isTest: false
        )) {
            $this->messageStatusUpdater->update($campaign, MessageStatus::Suspended);

            return;
        }

        $this->adminNotifier->notifyStart($campaign, $loadedMessageData, $data->getMessageId());

        // Campaign was already atomically claimed into Prepared status above.
        $excludeListIds = $this->exclusionService->resolveExcludeListIds($loadedMessageData);
        $this->exclusionService->markExcludedSubscribers($campaign, $data, $excludeListIds);
        $subscribers = $this->subscriberProvider->getSubscribersForMessageOrLists(
            $data,
            $campaign,
            $excludeListIds
        );

        $this->messageStatusUpdater->update($campaign, MessageStatus::InProcess);

        $stoppedEarly = $this->sendingLoop->run($campaign, $subscribers, $cacheKey);

        if ($stoppedEarly && $this->requeueHandler->handle($campaign)) {
            $this->entityManager->flush();
            return;
        }

        $this->messageStatusUpdater->update($campaign, MessageStatus::Sent);
    }
}
