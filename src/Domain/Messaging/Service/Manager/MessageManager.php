<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Manager;

use InvalidArgumentException;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Messaging\Model\Dto\MessageContext;
use PhpList\Core\Domain\Messaging\Model\Dto\MessageDtoInterface;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\MessageMetadata;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Service\Builder\MessageBuilder;

class MessageManager
{
    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly MessageBuilder $messageBuilder,
    ) {
    }

    public function createMessage(MessageDtoInterface $createMessageDto, Administrator $authUser): Message
    {
        $context = new MessageContext($authUser);
        $message = $this->messageBuilder->build($createMessageDto, $context);
        $this->messageRepository->persist($message);

        return $message;
    }

    public function copyAsDraftMessage(Message $message, Administrator $authUser): Message
    {
        $newMessage = new Message(
            format: new Message\MessageFormat(
                htmlFormatted: $message->getFormat()->isHtmlFormatted(),
                sendFormat: $message->getFormat()->getSendFormat()
            ),
            schedule: clone $message->getSchedule(),
            metadata: new MessageMetadata(status: Message\MessageStatus::Draft),
            content: clone $message->getContent(),
            options: clone $message->getOptions(),
            owner: $authUser,
            template: $message->getTemplate(),
        );
        $newMessage->setUuid(bin2hex(random_bytes(18)));

        $this->messageRepository->persist($newMessage);

        return $newMessage;
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
        if ($status === Message\MessageStatus::Submitted && !$this->canBeSubmitted($message)) {
            throw new InvalidArgumentException(
                'Cannot set status to submitted: add at least one list and fill subject, from field, and message body.'
            );
        }

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

    private function canBeSubmitted(Message $message): bool
    {
        return $message->getListMessages()->count() > 0
            && $this->isFilled($message->getContent()->getSubject())
            && $this->isFilled($message->getOptions()->getFromField())
            && $this->isFilled($message->getContent()->getText());
    }

    private function isFilled(?string $value): bool
    {
        return !empty(trim((string) $value));
    }
}
