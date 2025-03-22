<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Identity;

use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;

#[ORM\Entity]
#[ORM\Table(name: 'phplist_user_blacklist_data')]
#[ORM\Index(name: 'emailidx', columns: ['email'])]
#[ORM\Index(name: 'emailnameidx', columns: ['email', 'name'])]
class UserBlacklistData implements DomainModel
{
    #[ORM\Id]
    #[ORM\Column(name: 'email', type: 'string', length: 150)]
    private string $email;

    #[ORM\Column(name: 'name', type: 'string', length: 25)]
    private string $name;

    #[ORM\Column(name: 'data', type: 'text', nullable: true)]
    private ?string $data = null;

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getData(): ?string
    {
        return $this->data;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function setData(?string $data): self
    {
        $this->data = $data;
        return $this;
    }
}
