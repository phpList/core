<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Model;

use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Configuration\Repository\ConfigRepository;

#[ORM\Entity(repositoryClass: ConfigRepository::class)]
#[ORM\Table(name: 'phplist_config')]
class Config implements DomainModel
{
    #[ORM\Id]
    #[ORM\Column(name: 'item', type: 'string', length: 35)]
    private string $key;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $value = null;

    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    private bool $editable = true;

    #[ORM\Column(type: 'string', length: 25, nullable: true)]
    private ?string $type = null;

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): self
    {
        $this->key = $key;
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
