<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model;

use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Common\Model\Interfaces\Identity;
use PhpList\Core\Domain\Common\Model\Interfaces\ModificationDate;
use PhpList\Core\Domain\Messaging\Repository\ListMessageRepository;

#[ORM\Entity(repositoryClass: ListMessageRepository::class)]
#[ORM\Table(name: 'phplist_listmessage')]
#[ORM\UniqueConstraint(name: 'messageid', columns: ['messageid', 'listid'])]
#[ORM\Index(name: 'listmessageidx', columns: ['listid', 'messageid'])]
#[ORM\HasLifecycleCallbacks]
class ListMessage implements DomainModel, Identity, ModificationDate
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(name: 'messageid', type: 'integer')]
    private int $messageId;

    #[ORM\Column(name: 'listid', type: 'integer')]
    private int $listId;

    #[ORM\Column(name: 'entered', type: 'datetime', nullable: true)]
    private ?DateTimeInterface $entered = null;

    #[ORM\Column(name: 'modified', type: 'datetime')]
    private ?DateTime $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getListId(): int
    {
        return $this->listId;
    }

    public function setListId(int $listId): self
    {
        $this->listId = $listId;
        return $this;
    }

    public function getEntered(): ?DateTimeInterface
    {
        return $this->entered;
    }

    public function setEntered(?DateTimeInterface $entered): self
    {
        $this->entered = $entered;
        return $this;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateUpdatedAt(): DomainModel
    {
        $this->updatedAt = new DateTime();

        return $this;
    }
}
