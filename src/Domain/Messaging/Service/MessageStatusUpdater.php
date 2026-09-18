<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\MessageStatus;

class MessageStatusUpdater
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function update(Message $message, MessageStatus $status): void
    {
        if ($status === MessageStatus::InProcess && $message->getMetadata()->getSendStart() === null) {
            $message->getMetadata()->setSendStart(new DateTime());
        }
        if ($status === MessageStatus::Sent) {
            $message->getMetadata()->setSent(new DateTime());
        }
        $message->getMetadata()->setStatus($status);
        $this->entityManager->flush();
    }
}
