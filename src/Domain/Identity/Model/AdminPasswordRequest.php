<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Model;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Common\Model\Interfaces\Identity;
use PhpList\Core\Domain\Identity\Repository\AdminPasswordRequestRepository;

#[ORM\Entity(repositoryClass: AdminPasswordRequestRepository::class)]
#[ORM\Table(name: 'phplist_admin_password_request')]
class AdminPasswordRequest implements DomainModel, Identity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_key', type: 'integer', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(name: 'date', type: 'datetime', nullable: true)]
    private ?DateTime $date;

    #[ORM\ManyToOne(targetEntity: Administrator::class)]
    #[ORM\JoinColumn(name: 'admin', referencedColumnName: 'id', nullable: true)]
    private Administrator $administrator;

    #[ORM\Column(name: 'key_value', type: 'string', length: 32)]
    private string $keyValue;

    public function __construct(?DateTime $date, Administrator $admin, string $keyValue)
    {
        $this->date = $date;
        $this->administrator = $admin;
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

    public function getAdmin(): Administrator
    {
        return $this->administrator;
    }

    public function getKeyValue(): string
    {
        return $this->keyValue;
    }
}
