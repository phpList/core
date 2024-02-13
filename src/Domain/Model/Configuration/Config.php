<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Configuration;

use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Repository\Configuration\ConfigRepository;

#[ORM\Entity(repositoryClass: ConfigRepository::class)]
#[ORM\Table(name: "phplist_config")]
class Config implements DomainModel
{
    #[ORM\Id]
    #[ORM\Column(type: "string", length: 35)]
    private string $item;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $value = null;

    #[ORM\Column(type: "boolean", options: ["default" => 1])]
    private bool $editable = true;

    #[ORM\Column(type: "string", length: 25, nullable: true)]
    private ?string $type = null;

    public function getItem(): string
    {
        return $this->item;
    }

    public function setItem(string $item): self
    {
        $this->item = $item;
        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): self
    {
        $this->value = $value;
        return $this;
    }

    public function isEditable(): bool
    {
        return $this->editable;
    }

    public function setEditable(bool $editable): self
    {
        $this->editable = $editable;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;
        return $this;
    }
}
