<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Identity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Model\Interfaces\Identity;
use PhpList\Core\Domain\Repository\Identity\AdminPasswordRequestRepository;

#[ORM\Entity(repositoryClass: AdminPasswordRequestRepository::class)]
#[ORM\Table(name: 'phplist_admin_password_request')]
#[ORM\HasLifecycleCallbacks]
class AdminPasswordRequest implements DomainModel, Identity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_key', type: 'integer', options: ['unsigned' => true])]
    private int $id;

    #[ORM\Column(name: 'date', type: 'datetime', nullable: true)]
    private ?DateTime $date;

    #[ORM\Column(name: 'admin', type: 'integer', nullable: true, options: ['unsigned' => true])]
    private ?int $adminId;

    #[ORM\Column(name: 'key_value', type: 'string', length: 32)]
    private string $keyValue;

    public function __construct(?DateTime $date, ?int $adminId, string $keyValue)
    {
        $this->date = $date;
        $this->adminId = $adminId;
        $this->keyValue = $keyValue;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getDate(): DateTime
    {
        return $this->date;
    }

    public function getAdminId(): int
    {
        return $this->adminId;
    }

    public function getKeyValue(): string
    {
        return $this->keyValue;
    }
}
