<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Configuration;

use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Model\Interfaces\Identity;
use PhpList\Core\Domain\Model\Traits\IdentityTrait;
use PhpList\Core\Domain\Repository\Configuration\EventLogRepository;

#[ORM\Entity(repositoryClass: EventLogRepository::class)]
#[ORM\Table(name: "phplist_eventlog")]
#[ORM\Index(name: "enteredidx", columns: ["entered"])]
#[ORM\Index(name: "pageidx", columns: ["page"])]
class EventLog implements DomainModel, Identity
{
    use IdentityTrait;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?DateTimeInterface $entered = null;

    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private ?string $page = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $entry = null;

    public function getId(): int
    {
        return $this->id;
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

    public function getPage(): ?string
    {
        return $this->page;
    }

    public function setPage(?string $page): self
    {
        $this->page = $page;
        return $this;
    }

    public function getEntry(): ?string
    {
        return $this->entry;
    }

    public function setEntry(?string $entry): self
    {
        $this->entry = $entry;
        return $this;
    }
}
