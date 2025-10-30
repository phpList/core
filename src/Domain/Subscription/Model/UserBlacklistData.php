<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Model;

use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Subscription\Repository\UserBlacklistDataRepository;

#[ORM\Entity(repositoryClass: UserBlacklistDataRepository::class)]
#[ORM\Table(name: 'phplist_user_blacklist_data')]
#[ORM\Index(name: 'phplist_user_blacklist_data_emailidx', columns: ['email'])]
#[ORM\Index(name: 'phplist_user_blacklist_data_emailnameidx', columns: ['email', 'name'])]
class UserBlacklistData implements DomainModel
{
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: UserBlacklist::class, inversedBy: 'blacklistData')]
    #[ORM\JoinColumn(name: 'email', referencedColumnName: 'email', nullable: false, onDelete: 'CASCADE')]
    private UserBlacklist $blacklist;

    #[ORM\Column(name: 'name', type: 'string', length: 25)]
    private string $name;

    #[ORM\Column(name: 'data', type: 'text', nullable: true)]
    private ?string $data = null;

    public function getBlacklist(): UserBlacklist
    {
        return $this->blacklist;
    }

    public function setBlacklist(UserBlacklist $blacklist): self
    {
        $this->blacklist = $blacklist;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->blacklist->getEmail();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getData(): ?string
    {
        return $this->data;
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
