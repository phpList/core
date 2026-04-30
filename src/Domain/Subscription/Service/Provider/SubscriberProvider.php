<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Provider;

use PhpList\Core\Domain\Messaging\Message\CampaignProcessor\CampaignProcessorMessageInterface;
use PhpList\Core\Domain\Messaging\Message\CampaignProcessor\TestCampaignProcessorMessage;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberListRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;

class SubscriberProvider
{
    private SubscriberRepository $subscriberRepository;
    private SubscriberListRepository $subscriberListRepository;

    public function __construct(
        SubscriberRepository $subscriberRepository,
        SubscriberListRepository $subscriberListRepository,
    ) {
        $this->subscriberRepository = $subscriberRepository;
        $this->subscriberListRepository = $subscriberListRepository;
    }

    /**
     * Get subscribers for a message
     *
     * @param CampaignProcessorMessageInterface $data
     * @param Message $campaign
     * @return Subscriber[] Array of subscribers
     */
    public function getSubscribersForMessageOrLists(CampaignProcessorMessageInterface $data, Message $campaign): array
    {
        if ($data instanceof TestCampaignProcessorMessage) {
            return $this->subscriberRepository->getByEmails($data->getSubscriberEmails());
        }

        if (count($data->getListIds()) > 0) {
            $listIds = $data->getListIds();
        } else {
            $listIds = $this->subscriberListRepository->getListIdsByMessage($campaign);
        }

        $subscribers = [];
        foreach ($listIds as $listId) {
            $listSubscribers = $this->subscriberRepository->getSubscribersBySubscribedListId($listId);
            foreach ($listSubscribers as $subscriber) {
                $subscribers[$subscriber->getEmail()] = $subscriber;
            }
        }

        return array_values($subscribers);
    }
}
