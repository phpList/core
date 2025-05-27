<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Model;

use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Common\Model\Interfaces\Identity;
use PhpList\Core\Domain\Identity\Repository\AdminAttributeDefinitionRepository;

#[ORM\Entity(repositoryClass: AdminAttributeDefinitionRepository::class)]
#[ORM\Table(name: 'phplist_adminattribute')]
#[ORM\HasLifecycleCallbacks]
class AdminAttributeDefinition implements DomainModel, Identity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(name: 'name', type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(name: 'type', type: 'string', length: 30, nullable: true)]
    private ?string $type;

    #[ORM\Column(name: 'listorder', type: 'integer', nullable: true)]
    private ?int $listOrder;

    #[ORM\Column(name: 'default_value', type: 'string', length: 255, nullable: true)]
    private ?string $defaultValue;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $required;

    #[ORM\Column(name:'tablename', type: 'string', length: 255, nullable: true)]
    private ?string $tableName;

    public function __construct(
        string $name,
        ?string $type = null,
        ?int $listOrder = null,
        ?string $defaultValue = null,
        ?bool $required = null,
        ?string $tableName = null
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->listOrder = $listOrder;
        $this->defaultValue = $defaultValue;
        $this->required = $required;
        $this->tableName = $tableName;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

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
