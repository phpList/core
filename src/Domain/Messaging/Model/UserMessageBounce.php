<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Common\Model\Interfaces\Identity;
use PhpList\Core\Domain\Messaging\Repository\UserMessageBounceRepository;

#[ORM\Entity(repositoryClass: UserMessageBounceRepository::class)]
#[ORM\Table(name: 'phplist_user_message_bounce')]
#[ORM\Index(name: 'phplist_user_message_bounce_bounceidx', columns: ['bounce'])]
#[ORM\Index(name: 'phplist_user_message_bounce_msgidx', columns: ['message'])]
#[ORM\Index(name: 'phplist_user_message_bounce_umbindex', columns: ['user', 'message', 'bounce'])]
#[ORM\Index(name: 'phplist_user_message_bounce_useridx', columns: ['user'])]
class UserMessageBounce implements DomainModel, Identity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(name: 'user', type: 'integer')]
    private int $userId;

    #[ORM\Column(name: 'message', type: 'integer')]
    private int $messageId;

    #[ORM\Column(name: 'bounce', type: 'integer')]
    private int $bounceId;

    #[ORM\Column(name: 'time', type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private DateTime $createdAt;

    public function __construct(int $bounceId, DateTime $createdAt)
    {
        $this->bounceId = $bounceId;
        $this->createdAt = $createdAt;
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

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    public function setMessageId(int $messageId): self
    {
        $this->messageId = $messageId;
        return $this;
    }

    public function setBounceId(int $bounceId): self
    {
        $this->bounceId = $bounceId;
        return $this;
    }
}
