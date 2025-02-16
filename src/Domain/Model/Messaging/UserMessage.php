<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Messaging;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Model\Subscription\Subscriber;

#[ORM\Entity]
#[ORM\Table(name: "phplist_usermessage")]
#[ORM\Index(name: "enteredindex", columns: ["entered"])]
#[ORM\Index(name: "messageidindex", columns: ["messageid"])]
#[ORM\Index(name: "statusidx", columns: ["status"])]
#[ORM\Index(name: "useridindex", columns: ["userid"])]
#[ORM\Index(name: "viewedidx", columns: ["viewed"])]
class UserMessage implements DomainModel
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Subscriber::class)]
    #[ORM\JoinColumn(name: "userid", referencedColumnName: "id", nullable: false, onDelete: "CASCADE")]
    private Subscriber $user;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Message::class)]
    #[ORM\JoinColumn(name: "messageid", referencedColumnName: "id", nullable: false, onDelete: "CASCADE")]
    private Message $message;

    #[ORM\Column(name: "entered", type: "datetime")]
    private DateTime $entered;

    #[ORM\Column(name: "viewed", type: "datetime", nullable: true)]
    private ?DateTime $viewed = null;

    #[ORM\Column(name: "status", type: "string", length: 255, nullable: true)]
    private ?string $status = null;

    public function getUser(): Subscriber
    {
        return $this->user;
    }

    public function getMessage(): Message
    {
        return $this->message;
    }

    public function getEntered(): DateTime
    {
        return $this->entered;
    }

    public function getViewed(): ?DateTime
    {
        return $this->viewed;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setUser(Subscriber $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function setMessage(Message $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function setEntered(DateTime $entered): self
    {
        $this->entered = $entered;
        return $this;
    }

    public function setViewed(?DateTime $viewed): self
    {
        $this->viewed = $viewed;
        return $this;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;
        return $this;
    }
}
