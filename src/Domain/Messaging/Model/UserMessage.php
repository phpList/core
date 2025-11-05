<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Messaging\Model\Message\UserMessageStatus;
use PhpList\Core\Domain\Messaging\Repository\UserMessageRepository;
use PhpList\Core\Domain\Subscription\Model\Subscriber;

#[ORM\Entity(repositoryClass: UserMessageRepository::class)]
#[ORM\Table(name: 'phplist_usermessage')]
#[ORM\Index(name: 'phplist_usermessage_enteredindex', columns: ['entered'])]
#[ORM\Index(name: 'phplist_usermessage_messageidindex', columns: ['messageid'])]
#[ORM\Index(name: 'phplist_usermessage_statusidx', columns: ['status'])]
#[ORM\Index(name: 'phplist_usermessage_useridindex', columns: ['userid'])]
#[ORM\Index(name: 'phplist_usermessage_viewedidx', columns: ['viewed'])]
class UserMessage implements DomainModel
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Subscriber::class)]
    #[ORM\JoinColumn(name: 'userid', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Subscriber $user;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Message::class)]
    #[ORM\JoinColumn(name: 'messageid', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Message $message;

    #[ORM\Column(name: 'entered', type: 'datetime')]
    private DateTime $createdAt;

    #[ORM\Column(name: 'viewed', type: 'datetime', nullable: true)]
    private ?DateTime $viewed = null;

    #[ORM\Column(name: 'status', type: 'string', length: 255, nullable: true)]
    private ?string $status = null;

    public function __construct(Subscriber $user, Message $message)
    {
        $this->user = $user;
        $this->message = $message;
        $this->createdAt = new DateTime();
    }

    public function getUser(): Subscriber
    {
        return $this->user;
    }

    public function getMessage(): Message
    {
        return $this->message;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getViewed(): ?DateTime
    {
        return $this->viewed;
    }

    /**
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    public function getStatus(): ?UserMessageStatus
    {
        return UserMessageStatus::from($this->status);
    }

    public function setViewed(?DateTime $viewed): self
    {
        $this->viewed = $viewed;
        return $this;
    }

    public function setStatus(?UserMessageStatus $status): self
    {
        $this->status = $status->value;
        return $this;
    }
}
