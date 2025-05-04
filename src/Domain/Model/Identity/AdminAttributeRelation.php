<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Identity;

use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Repository\Identity\AdminAttributeRelationRepository;

#[ORM\Entity(repositoryClass: AdminAttributeRelationRepository::class)]
#[ORM\Table(name: 'phplist_admin_attribute')]
#[ORM\HasLifecycleCallbacks]
class AdminAttributeRelation implements DomainModel
{
    #[ORM\Id]
    #[ORM\Column(name: 'adminattributeid', type: 'integer', options: ['unsigned' => true])]
    private int $adminAttributeId;

    #[ORM\Id]
    #[ORM\Column(name: 'adminid', type: 'integer', options: ['unsigned' => true])]
    private int $adminId;

    #[ORM\Column(name: 'value', type: 'string', length: 255, nullable: true)]
    private ?string $value;

    public function __construct(int $adminAttributeId, int $adminId, ?string $value = null)
    {
        $this->adminAttributeId = $adminAttributeId;
        $this->adminId = $adminId;
        $this->value = $value;
    }

    public function getAdminAttributeId(): int
    {
        return $this->adminAttributeId;
    }

    public function getAdminId(): int
    {
        return $this->adminId;
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
