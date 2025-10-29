<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Analytics\Model;

use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Analytics\Repository\UserStatsRepository;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Common\Model\Interfaces\Identity;

#[ORM\Entity(repositoryClass: UserStatsRepository::class)]
#[ORM\Table(name: 'phplist_userstats')]
#[ORM\UniqueConstraint(name: 'entry', columns: ['unixdate', 'item', 'listid'])]
#[ORM\Index(name: 'dateindex', columns: ['unixdate'])]
#[ORM\Index(name: 'itemindex', columns: ['item'])]
#[ORM\Index(name: 'listdateindex', columns: ['listid', 'unixdate'])]
#[ORM\Index(name: 'listindex', columns: ['listid'])]
class UserStats implements DomainModel, Identity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(name: 'unixdate', type: 'integer', nullable: true)]
    private ?int $unixDate = null;

    #[ORM\Column(name: 'item', type: 'string', length: 255, nullable: true)]
    private ?string $item = null;

    #[ORM\Column(name: 'listid', type: 'integer', nullable: true, options: ['default' => 0])]
    private int $listId = 0;

    #[ORM\Column(name: 'value', type: 'integer', nullable: true, options: ['default' => 0])]
    private ?int $value = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUnixDate(): ?int
    {
        return $this->unixDate;
    }

    public function getItem(): ?string
    {
        return $this->item;
    }

    public function getListId(): int
    {
        return $this->listId;
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function setUnixDate(?int $unixDate): self
    {
        $this->unixDate = $unixDate;
        return $this;
    }

    public function setItem(?string $item): self
    {
        $this->item = $item;
        return $this;
    }

    public function setListId(int $listId): self
    {
        $this->listId = $listId;
        return $this;
    }

    public function setValue(int $value): self
    {
        $this->value = $value;
        return $this;
    }
}
