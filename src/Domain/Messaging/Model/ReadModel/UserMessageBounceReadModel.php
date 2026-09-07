<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model\ReadModel;

use DateTime;
use PhpList\Core\Domain\Messaging\Model\Interfaces\UserMessageBounceRecordInterface;

/** Flat projection of a bounce-link row as read back from Elasticsearch - no Doctrine association. */
class UserMessageBounceReadModel implements UserMessageBounceRecordInterface
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $userId,
        private readonly int $messageId,
        private readonly int $bounceId,
        private readonly DateTime $createdAt,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getMessageId(): int
    {
        return $this->messageId;
    }

    public function getBounceId(): int
    {
        return $this->bounceId;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }
}
