<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Provider;

use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Repository\ListMessageRepository;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;

class SubscriberProvider
{
    private ListMessageRepository $listMessageRepository;
    private SubscriberRepository $subscriberRepository;

    public function __construct(
        ListMessageRepository $listMessageRepository,
        SubscriberRepository $subscriberRepository
    ) {
        $this->listMessageRepository = $listMessageRepository;
        $this->subscriberRepository = $subscriberRepository;
    }

    /**
     * Get subscribers for a message
     *
     * @param Message $message The message to get subscribers for
     * @return Subscriber[] Array of subscribers
     */
    public function getSubscribersForMessage(Message $message): array
    {
        $listIds = $this->listMessageRepository->getListIdsByMessageId($message->getId());

        $subscribers = [];
        foreach ($listIds as $listId) {
            $listSubscribers = $this->subscriberRepository->getSubscribersBySubscribedListId($listId);
            foreach ($listSubscribers as $subscriber) {
                $subscribers[$subscriber->getId()] = $subscriber;
            }
        }

        return array_values($subscribers);
    }
}
