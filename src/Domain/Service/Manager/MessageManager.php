<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Service\Manager;

use PhpList\Core\Domain\Model\Dto\MessageContext;
use PhpList\Core\Domain\Model\Identity\Administrator;
use PhpList\Core\Domain\Model\Messaging\Dto\MessageDtoInterface;
use PhpList\Core\Domain\Model\Messaging\Message;
use PhpList\Core\Domain\Repository\Messaging\MessageRepository;
use PhpList\Core\Domain\Service\Builder\MessageBuilder;

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
        $this->messageRepository->save($message);

        return $message;
    }

    public function updateMessage(
        MessageDtoInterface $updateMessageDto,
        Message $message,
        Administrator $authUser
    ): Message {
        $context = new MessageContext($authUser, $message);
        $message = $this->messageBuilder->build($updateMessageDto, $context);
        $this->messageRepository->save($message);

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
