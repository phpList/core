<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Model;

use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Common\Model\Interfaces\Identity;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeDefinitionRepository;

#[ORM\Entity(repositoryClass: SubscriberAttributeDefinitionRepository::class)]
#[ORM\Table(name: 'phplist_user_attribute')]
#[ORM\Index(name: 'phplist_user_attribute_idnameindex', columns: ['id', 'name'])]
#[ORM\Index(name: 'phplist_user_attribute_nameindex', columns: ['name'])]
class SubscriberAttributeDefinition implements DomainModel, Identity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(name: 'name', type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(name: 'type', type: 'string', length: 30, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(name: 'listorder', type: 'integer', nullable: true)]
    private ?int $listOrder = null;

    #[ORM\Column(name: 'default_value', type: 'string', length: 255, nullable: true)]
    private ?string $defaultValue = null;

    #[ORM\Column(name: 'required', type: 'boolean', nullable: true)]
    private ?bool $required = null;

    #[ORM\Column(name: 'tablename', type: 'string', length: 255, nullable: true)]
    private ?string $tableName = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @SuppressWarnings(PHPMD.StaticAccess) */
    public function getType(): ?AttributeTypeEnum
    {
        return $this->type === null ? null : AttributeTypeEnum::from($this->type);
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

    /** @SuppressWarnings(PHPMD.StaticAccess) */
    public function setType(?AttributeTypeEnum $type): self
    {
        if ($type === null) {
            $this->type = null;
            return $this;
        }

        if ($this->type !== null) {
            $currentType = AttributeTypeEnum::from($this->type);
            $currentType->assertTransitionAllowed($type);
        }
        $this->type = $type->value;

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
