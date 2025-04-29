<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Messaging;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Model\Interfaces\Identity;

#[ORM\Entity]
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
    private int $message;

    #[ORM\Column(name: 'forward', type: 'string', length: 255, nullable: true)]
    private ?string $forward = null;

    #[ORM\Column(name: 'status', type: 'string', length: 255, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(name: 'time', type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private DateTime $time;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): int
    {
        return $this->user;
    }

    public function getMessage(): int
    {
        return $this->message;
    }

    public function getForward(): ?string
    {
        return $this->forward;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getTime(): DateTime
    {
        return $this->time;
    }

    public function setUser(int $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function setMessage(int $message): self
    {
        $this->message = $message;
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

    public function setTime(DateTime $time): self
    {
        $this->time = $time;
        return $this;
    }
}
