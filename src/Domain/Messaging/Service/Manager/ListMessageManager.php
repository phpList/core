<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Manager;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Messaging\Model\ListMessage;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Repository\ListMessageRepository;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Subscription\Model\SubscriberList;
use PhpList\Core\Domain\Subscription\Repository\SubscriberListRepository;

class ListMessageManager
{
    private ListMessageRepository $listMessageRepository;
    private MessageRepository $messageRepository;
    private SubscriberListRepository $subscriberListRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(
        ListMessageRepository $listMessageRepository,
        MessageRepository $messageRepository,
        SubscriberListRepository $subscriberListRepository,
        EntityManagerInterface $entityManager,
    ) {
        $this->listMessageRepository = $listMessageRepository;
        $this->messageRepository = $messageRepository;
        $this->subscriberListRepository = $subscriberListRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * Associates a message with a subscriber list
     */
    public function associateMessageWithList(Message $message, SubscriberList $subscriberList): ListMessage
    {
        $listMessage = new ListMessage();
        $listMessage->setMessage($message);
        $listMessage->setList($subscriberList);
        $listMessage->setEntered(new DateTime());

        $this->entityManager->persist($listMessage);

        return $listMessage;
    }

    /**
     * Removes the association between a message and a subscriber list
     */
    public function removeAssociation(Message $message, SubscriberList $subscriberList): void
    {
        $relation = $this->listMessageRepository->getByMessageAndList($message, $subscriberList);
        $this->entityManager->remove($relation);
    }

    /**
     * Checks if a message is associated with a subscriber list
     */
    public function isMessageAssociatedWithList(Message $message, SubscriberList $subscriberList): bool
    {
        return $this->listMessageRepository->isMessageAssociatedWithList($message, $subscriberList);
    }

    /**
     * Associates a message with multiple subscriber lists
     *
     * @param SubscriberList[] $subscriberLists
     */
    public function associateMessageWithLists(Message $message, array $subscriberLists): void
    {
        foreach ($subscriberLists as $subscriberList) {
            $this->associateMessageWithList($message, $subscriberList);
        }
    }

    /**
     * Removes all list associations for a message
     */
    public function removeAllListAssociationsForMessage(Message $message): void
    {
        $this->listMessageRepository->removeAllListAssociationsForMessage($message);
    }

    public function getMessagesByList(SubscriberList $list): array
    {
        return $this->messageRepository->getMessagesByList($list);
    }

    public function getListByMessage(Message $message): array
    {
        return $this->subscriberListRepository->getListsByMessage($message);
    }
}
