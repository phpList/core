<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Manager;

use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Messaging\Model\Dto\MessageContext;
use PhpList\Core\Domain\Messaging\Model\Dto\MessageDtoInterface;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Service\Builder\MessageBuilder;

class MessageManager
{
    private MessageRepository $messageRepository;
    private MessageBuilder $messageBuilder;

    public function __construct(MessageRepository $messageRepository, MessageBuilder $messageBuilder)
    {
        $this->messageRepository = $messageRepository;
        $this->messageBuilder = $messageBuilder;
    }

    public function createMessage(MessageDtoInterface $createMessageDto, Administrator $authUser): Message
    {
        $context = new MessageContext($authUser);
        $message = $this->messageBuilder->build($createMessageDto, $context);
        $this->messageRepository->persist($message);

        return $message;
    }

    public function updateMessage(
        MessageDtoInterface $updateMessageDto,
        Message $message,
        Administrator $authUser
    ): Message {
        $context = new MessageContext($authUser, $message);
        return $this->messageBuilder->build($updateMessageDto, $context);
    }

    public function updateStatus(Message $message, Message\MessageStatus $status): Message
    {
        $message->getMetadata()->setStatus($status);

        return $message;
    }

    public function delete(Message $message): void
    {
        $this->messageRepository->remove($message);
    }

    /** @return Message[] */
    public function getMessagesByOwner(Administrator $owner): array
    {
        return $this->messageRepository->getByOwnerId($owner->getId());
    }
}
