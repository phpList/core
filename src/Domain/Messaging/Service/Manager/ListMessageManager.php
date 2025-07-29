<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Manager;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Messaging\Model\ListMessage;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Repository\ListMessageRepository;
use PhpList\Core\Domain\Subscription\Model\SubscriberList;

class ListMessageManager
{
    private ListMessageRepository $listMessageRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(
        ListMessageRepository $listMessageRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->listMessageRepository = $listMessageRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * Associates a message with a subscriber list
     */
    public function associateMessageWithList(Message $message, SubscriberList $subscriberList): ListMessage
    {
        $listMessage = new ListMessage();
        $listMessage->setMessageId($message->getId());
        $listMessage->setListId($subscriberList->getId());
        $listMessage->setEntered(new DateTime());

        $this->entityManager->persist($listMessage);
        $this->entityManager->flush();

        return $listMessage;
    }

    /**
     * Removes the association between a message and a subscriber list
     */
    public function removeAssociation(Message $message, SubscriberList $subscriberList): void
    {
        $relation = $this->listMessageRepository->getByMessageIdAndListId($message->getId(), $subscriberList->getId());
        $this->entityManager->remove($relation);
    }

    /**
     * Gets all subscriber lists associated with a message
     *
     * @return int[]
     */
    public function getListIdsByMessage(Message $message): array
    {
        return $this->listMessageRepository->getListIdsByMessageId($message->getId());
    }

    /**
     * Gets all messages associated with a subscriber list
     *
     * @return int[]
     */
    public function getMessageIdsByList(SubscriberList $subscriberList): array
    {
        return $this->listMessageRepository->getMessageIdsByListId($subscriberList->getId());
    }

    /**
     * Checks if a message is associated with a subscriber list
     */
    public function isMessageAssociatedWithList(Message $message, SubscriberList $subscriberList): bool
    {
        return $this->listMessageRepository->isMessageAssociatedWithList($message->getId(), $subscriberList->getId());
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
        $this->listMessageRepository->removeAllListAssociationsForMessage($message->getId());
    }
}
