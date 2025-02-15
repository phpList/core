<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Messaging;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Model\Interfaces\Identity;
use PhpList\Core\Domain\Model\Interfaces\ModificationDate;
use PhpList\Core\Domain\Model\Traits\IdentityTrait;
use PhpList\Core\Domain\Model\Traits\ModificationDateTrait;

#[ORM\Entity]
#[ORM\Table(name: "phplist_sendprocess")]
class SendProcess implements DomainModel, Identity, ModificationDate
{
    use IdentityTrait;
    use ModificationDateTrait;

    #[ORM\Column(name: "started", type: "datetime", nullable: true)]
    private ?DateTime $started = null;

    #[ORM\Column(name: "modified", type: "datetime", options: ["default" => "CURRENT_TIMESTAMP"])]
    private ?DateTime $modificationDate = null;

    #[ORM\Column(name: "alive", type: "integer", nullable: true, options: ["default" => 1])]
    private ?int $alive = 1;

    #[ORM\Column(name: "ipaddress", type: "string", length: 50, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(name: "page", type: "string", length: 100, nullable: true)]
    private ?string $page = null;

    public function getStartedDate(): ?DateTime
    {
        return $this->started;
    }

    public function setStartedDate(DateTime $started): self
    {
        $this->started = $started;
        return $this;
    }

    public function getModificationDate(): ?DateTime
    {
        return $this->modificationDate;
    }

    public function getAlive(): ?int
    {
        return $this->alive;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getPage(): ?string
    {
        return $this->page;
    }

    public function setAlive(?int $alive): self
    {
        $this->alive = $alive;
        return $this;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function setPage(?string $page): self
    {
        $this->page = $page;
        return $this;
    }
}
