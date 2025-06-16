<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Common\Model\Interfaces\Identity;
use PhpList\Core\Domain\Messaging\Repository\UserMessageForwardRepository;

#[ORM\Entity(repositoryClass: UserMessageForwardRepository::class)]
#[ORM\Table(name: 'phplist_user_message_forward')]
#[ORM\Index(name: 'messageidx', columns: ['message'])]
#[ORM\Index(name: 'useridx', columns: ['user'])]
#[ORM\Index(name: 'usermessageidx', columns: ['user', 'message'])]
class UserMessageForward implements DomainModel, Identity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(name: 'user', type: 'integer')]
    private int $user;

    #[ORM\Column(name: 'message', type: 'integer')]
    private int $messageId;

    #[ORM\Column(name: 'forward', type: 'string', length: 255, nullable: true)]
    private ?string $forward = null;

    #[ORM\Column(name: 'status', type: 'string', length: 255, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(name: 'time', type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): int
    {
        return $this->user;
    }

    public function getMessageId(): int
    {
        return $this->messageId;
    }

    public function getForward(): ?string
    {
        return $this->forward;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function setUser(int $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function setMessageId(int $messageId): self
    {
        $this->messageId = $messageId;
        return $this;
    }

    public function setForward(?string $forward): self
    {
        $this->forward = $forward;
        return $this;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;
        return $this;
    }
}
