<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Messaging;

use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Model\Interfaces\Identity;
use PhpList\Core\Domain\Model\Interfaces\ModificationDate;
use PhpList\Core\Domain\Model\Traits\IdentityTrait;
use PhpList\Core\Domain\Model\Traits\ModificationDateTrait;
use PhpList\Core\Domain\Repository\Messaging\ListMessageRepository;

#[ORM\Entity(repositoryClass: ListMessageRepository::class)]
#[ORM\Table(name: "phplist_listmessage")]
#[ORM\UniqueConstraint(name: "messageid", columns: ["messageid", "listid"])]
#[ORM\Index(name: "listmessageidx", columns: ["listid", "messageid"])]
#[ORM\HasLifecycleCallbacks]
class ListMessage implements DomainModel, Identity, ModificationDate
{
    use IdentityTrait;
    use ModificationDateTrait;

    #[ORM\Column(name: 'messageid', type: "integer")]
    private int $messageId;

    #[ORM\Column(name: 'modified', type: 'datetime')]
    protected ?DateTime $modificationDate;

    #[ORM\Column(name: "listid", type: "integer")]
    private int $listId;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?DateTimeInterface $entered = null;

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
}
