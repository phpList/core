<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Identity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;

#[ORM\Entity]
#[ORM\Table(name: 'phplist_user_blacklist')]
#[ORM\Index(name: 'emailidx', columns: ['email'])]
class UserBlacklist implements DomainModel
{
    #[ORM\Id]
    #[ORM\Column(name: 'email', type: 'string', length: 255, unique: true)]
    private string $email;

    #[ORM\Column(name: 'added', type: 'datetime', nullable: true)]
    private ?DateTime $added = null;

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getAdded(): ?DateTime
    {
        return $this->added;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function setAdded(?DateTime $added): self
    {
        $this->added = $added;
        return $this;
    }
}
