<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Provider;

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
     * @param Message $message The message to get subscribers for
     * @return Subscriber[] Array of subscribers
     */
    public function getSubscribersForMessage(Message $message): array
    {
        $lists = $this->subscriberListRepository->getListsByMessage($message);

        $subscribers = [];
        foreach ($lists as $list) {
            $listSubscribers = $this->subscriberRepository->getSubscribersBySubscribedListId($list->getId());
            foreach ($listSubscribers as $subscriber) {
                $subscribers[$subscriber->getId()] = $subscriber;
            }
        }

        return array_values($subscribers);
    }
}
