<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Analytics;

use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Repository\Analytics\LinkTrackUserClickRepository;

#[ORM\Entity(repositoryClass: LinkTrackUserClickRepository::class)]
#[ORM\Table(name: 'phplist_linktrack_userclick')]
#[ORM\Index(name: 'linkindex', columns: ['linkid'])]
#[ORM\Index(name: 'linkuserindex', columns: ['linkid', 'userid'])]
#[ORM\Index(name: 'linkusermessageindex', columns: ['linkid', 'userid', 'messageid'])]
#[ORM\Index(name: 'midindex', columns: ['messageid'])]
#[ORM\Index(name: 'uidindex', columns: ['userid'])]
class LinkTrackUserClick implements DomainModel
{
    #[ORM\Id]
    #[ORM\Column(name: 'linkid', type: 'integer')]
    private int $linkId;

    #[ORM\Id]
    #[ORM\Column(name: 'userid', type: 'integer')]
    private int $userId;

    #[ORM\Id]
    #[ORM\Column(name: 'messageid', type: 'integer')]
    private int $messageId;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $data = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTimeInterface $date = null;

    public function getLinkId(): int
    {
        return $this->linkId;
    }

    public function setLinkId(int $linkId): self
    {
        $this->linkId = $linkId;
        return $this;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    public function getMessageId(): int
    {
        return $this->messageId;
    }

    public function setMessageId(int $messageId): self
    {
        $this->messageId = $messageId;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getData(): ?string
    {
        return $this->data;
    }

    public function setData(?string $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function getDate(): ?DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(?DateTimeInterface $date): self
    {
        $this->date = $date;
        return $this;
    }
}
