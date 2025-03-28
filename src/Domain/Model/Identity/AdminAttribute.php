<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Identity;

use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Model\Interfaces\Identity;
use PhpList\Core\Domain\Model\Traits\IdentityTrait;
use PhpList\Core\Domain\Repository\Identity\AdminAttributeRepository;

#[ORM\Entity(repositoryClass: AdminAttributeRepository::class)]
#[ORM\Table(name: 'phplist_admin_attribute')]
#[ORM\HasLifecycleCallbacks]
class AdminAttribute implements DomainModel, Identity
{
    use IdentityTrait;

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

    public function getId(): int
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

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function setListOrder(?int $listOrder): void
    {
        $this->listOrder = $listOrder;
    }

    public function setDefaultValue(?string $defaultValue): void
    {
        $this->defaultValue = $defaultValue;
    }

    public function setRequired(?bool $required): void
    {
        $this->required = $required;
    }

    public function setTableName(?string $tableName): void
    {
        $this->tableName = $tableName;
    }
}
