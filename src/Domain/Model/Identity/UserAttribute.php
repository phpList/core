<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Identity;

use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Model\Interfaces\Identity;
use PhpList\Core\Domain\Model\Traits\IdentityTrait;

#[ORM\Entity]
#[ORM\Table(name: "phplist_user_attribute")]
#[ORM\Index(name: "idnameindex", columns: ["id", "name"])]
#[ORM\Index(name: "nameindex", columns: ["name"])]
class UserAttribute implements DomainModel, Identity
{
    use IdentityTrait;

    #[ORM\Column(name: "name", type: "string", length: 255)]
    private string $name;

    #[ORM\Column(name: "type", type: "string", length: 30, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(name: "listorder", type: "integer", nullable: true)]
    private ?int $listOrder = null;

    #[ORM\Column(name: "default_value", type: "string", length: 255, nullable: true)]
    private ?string $defaultValue = null;

    #[ORM\Column(name: "required", type: "boolean", nullable: true)]
    private ?bool $required = null;

    #[ORM\Column(name: "tablename", type: "string", length: 255, nullable: true)]
    private ?string $tableName = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getListOrder(): ?int
    {
        return $this->listOrder;
    }

    public function getDefaultValue(): ?string
    {
        return $this->defaultValue;
    }

    public function isRequired(): ?bool
    {
        return $this->required;
    }

    public function getTableName(): ?string
    {
        return $this->tableName;
    }

    // Setters
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function setListOrder(?int $listOrder): self
    {
        $this->listOrder = $listOrder;
        return $this;
    }

    public function setDefaultValue(?string $defaultValue): self
    {
        $this->defaultValue = $defaultValue;
        return $this;
    }

    public function setRequired(?bool $required): self
    {
        $this->required = $required;
        return $this;
    }

    public function setTableName(?string $tableName): self
    {
        $this->tableName = $tableName;
        return $this;
    }
}
