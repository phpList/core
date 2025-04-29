<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Analytics;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Model\Interfaces\Identity;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'phplist_user_message_view')]
#[ORM\Index(name: 'msgidx', columns: ['messageid'])]
#[ORM\Index(name: 'useridx', columns: ['userid'])]
#[ORM\Index(name: 'usermsgidx', columns: ['userid', 'messageid'])]
class UserMessageView implements DomainModel, Identity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    #[Groups(['SubscriberList', 'SubscriberListMembers'])]
    private ?int $id = null;

    #[ORM\Column(name: 'messageid', type: 'integer')]
    private int $messageId;

    #[ORM\Column(name: 'userid', type: 'integer')]
    private int $userId;

    #[ORM\Column(name: 'viewed', type: 'datetime', nullable: true)]
    private ?DateTime $viewed = null;

    #[ORM\Column(name: 'ip', type: 'string', length: 255, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(name: 'data', type: 'text', nullable: true)]
    private ?string $data = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessageId(): int
    {
        return $this->messageId;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getViewed(): ?DateTime
    {
        return $this->viewed;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function getData(): ?string
    {
        return $this->data;
    }

    public function setMessageId(int $messageId): self
    {
        $this->messageId = $messageId;
        return $this;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    public function setViewed(?DateTime $viewed): self
    {
        $this->viewed = $viewed;
        return $this;
    }

    public function setIp(?string $ip): self
    {
        $this->ip = $ip;
        return $this;
    }

    public function setData(?string $data): self
    {
        $this->data = $data;
        return $this;
    }
}
