<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Model;

use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Identity\Repository\AdminAttributeValueRepository;

#[ORM\Entity(repositoryClass: AdminAttributeValueRepository::class)]
#[ORM\Table(name: 'phplist_admin_attribute')]
#[ORM\HasLifecycleCallbacks]
class AdminAttributeValue implements DomainModel
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: AdminAttributeDefinition::class)]
    #[ORM\JoinColumn(name: 'adminattributeid', referencedColumnName: 'id', nullable: false)]
    private AdminAttributeDefinition $attributeDefinition;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Administrator::class)]
    #[ORM\JoinColumn(name: 'adminid', referencedColumnName: 'id', nullable: false)]
    private Administrator $administrator;

    #[ORM\Column(name: 'value', type: 'string', length: 255, nullable: true)]
    private ?string $value;

    public function __construct(
        AdminAttributeDefinition $attributeDefinition,
        Administrator $administrator,
        ?string $value = null
    ) {
        $this->attributeDefinition = $attributeDefinition;
        $this->administrator = $administrator;
        $this->value = $value;
    }

    public function getAttributeDefinition(): AdminAttributeDefinition
    {
        return $this->attributeDefinition;
    }

    public function getAdministrator(): Administrator
    {
        return $this->administrator;
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
}
