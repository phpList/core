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
#[ORM\Index(name: 'bounceidx', columns: ['bounce'])]
#[ORM\Index(name: 'msgidx', columns: ['message'])]
#[ORM\Index(name: 'umbindex', columns: ['user', 'message', 'bounce'])]
#[ORM\Index(name: 'useridx', columns: ['user'])]
#[ORM\HasLifecycleCallbacks]
class UserMessageBounce implements DomainModel, Identity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(name: 'user', type: 'integer')]
    private int $user;

    #[ORM\Column(name: 'message', type: 'integer')]
    private int $messageId;

    #[ORM\Column(name: 'bounce', type: 'integer')]
    private int $bounce;

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

    public function getBounce(): int
    {
        return $this->bounce;
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

    public function setBounce(int $bounce): self
    {
        $this->bounce = $bounce;
        return $this;
    }
}
