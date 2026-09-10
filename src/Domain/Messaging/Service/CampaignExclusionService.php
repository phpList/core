<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Messaging\Message\CampaignProcessor\CampaignProcessorMessage;
use PhpList\Core\Domain\Messaging\Message\CampaignProcessor\SyncCampaignProcessorMessage;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\UserMessageStatus;
use PhpList\Core\Domain\Messaging\Model\UserMessage;
use PhpList\Core\Domain\Messaging\Repository\UserMessageRepository;
use PhpList\Core\Domain\Subscription\Service\Provider\SubscriberProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class CampaignExclusionService
{
    public function __construct(
        private readonly SubscriberProvider $subscriberProvider,
        private readonly UserMessageRepository $userMessageRepository,
        #[Autowire('%messaging.use_list_exclude%')] private readonly bool $useListExclude = false,
    ) {
    }

    /**
     * Exclude-list IDs are stored via MessageData as an array keyed by list ID e.g. [3 => 1, 7 => 1].
     *
     * @return int[]
     */
    public function resolveExcludeListIds(array $loadedMessageData): array
    {
        if (!$this->useListExclude) {
            return [];
        }

        $excludeList = $loadedMessageData['excludelist'] ?? [];
        if (!is_array($excludeList) || $excludeList === []) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $key): ?int => is_numeric($key) ? (int) $key : null,
            array_keys($excludeList)
        ), static fn (?int $id): bool => $id !== null));
    }

    /**
     * pre-marking of exclude-list members as "excluded" in usermessage before the main send loop runs,
     * so there's a persisted audit trail for why a subscriber wasn't sent to. Skips
     * subscribers who already have a nontodo UserMessage for this campaign, so a later run
     * can't clobber an already-recorded Sent/NotSent/etc. status from an earlier partial run.
     * Only campaign recipients (i.e. subscribers who'd otherwise be sent this campaign) are
     * marked, since a subscriber on an exclude list who isn't a campaign recipient anyway
     * shouldn't get an exclusion record.
     */
    public function markExcludedSubscribers(
        Message $campaign,
        CampaignProcessorMessage|SyncCampaignProcessorMessage $data,
        array $excludeListIds,
    ): void {
        if ($excludeListIds === []) {
            return;
        }

        $excludedSubscribers = $this->subscriberProvider->getExcludedSubscribers($excludeListIds);
        if ($excludedSubscribers === []) {
            return;
        }

        $sendableSubscribers = $this->subscriberProvider->getSendableSubscribersForMessageOrLists(
            $data,
            $campaign
        );

        foreach ($excludedSubscribers as $subscriber) {
            if (!isset($sendableSubscribers[$subscriber->getEmail()])) {
                continue;
            }

            $existing = $this->userMessageRepository->findByUserAndMessage($subscriber, $campaign);
            if ($existing && $existing->getStatus() !== UserMessageStatus::Todo) {
                continue;
            }

            $userMessage = $existing ?? new UserMessage($subscriber, $campaign);
            $userMessage->setStatus(UserMessageStatus::Excluded);
            $this->userMessageRepository->save($userMessage);
        }
    }
}
