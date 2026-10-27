<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Analytics\Service\LinkTrackService;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MessageProcessingPreparator
{
    public function __construct(
        private readonly SubscriberRepository $subscriberRepository,
        private readonly MessageRepository $messageRepository,
        private readonly LinkTrackService $linkTrackService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function ensureSubscribersHaveUuid(OutputInterface $output): void
    {
        $subscribersWithoutUuid = $this->subscriberRepository->findSubscribersWithoutUuid();

        $numSubscribers = count($subscribersWithoutUuid);
        if ($numSubscribers > 0) {
            $output->writeln($this->translator->trans('Giving a UUID to %count% subscribers, this may take a while', [
                '%count%' => $numSubscribers
            ]));
            foreach ($subscribersWithoutUuid as $subscriber) {
                $subscriber->setUniqueId(bin2hex(random_bytes(16)));
            }
        }
    }

    public function ensureCampaignsHaveUuid(OutputInterface $output): void
    {
        $campaignsWithoutUuid = $this->messageRepository->findCampaignsWithoutUuid();

        $numCampaigns = count($campaignsWithoutUuid);
        if ($numCampaigns > 0) {
            $output->writeln($this->translator->trans('Giving a UUID to %count% campaigns', [
                '%count%' => $numCampaigns
            ]));
            foreach ($campaignsWithoutUuid as $campaign) {
                $campaign->setUuid(bin2hex(random_bytes(18)));
            }
        }
    }

    /**
     * Replaces the links of the cached message content with click-tracking links for this subscriber.
     * Returns the content unchanged when click tracking is off or the campaign is unknown.
     */
    public function processMessageLinks(
        int $campaignId,
        MessagePrecacheDto $cachedMessageDto,
        Subscriber $subscriber
    ): MessagePrecacheDto {
        if (!$this->linkTrackService->isClickTrackingEnabled()) {
            return $cachedMessageDto;
        }

        $campaign = $this->messageRepository->findById($campaignId);
        if ($campaign === null) {
            return $cachedMessageDto;
        }

        return $this->linkTrackService->trackLinks($cachedMessageDto, $campaign, $subscriber);
    }
}
