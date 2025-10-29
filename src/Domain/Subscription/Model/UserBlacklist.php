<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Model;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Subscription\Repository\UserBlacklistRepository;

#[ORM\Entity(repositoryClass: UserBlacklistRepository::class)]
#[ORM\Table(name: 'phplist_user_blacklist')]
#[ORM\Index(name: 'emailidx', columns: ['email'])]
class UserBlacklist implements DomainModel
{
    #[ORM\Id]
    #[ORM\Column(name: 'email', type: 'string', length: 255, unique: true)]
    private string $email;

    #[ORM\Column(name: 'added', type: 'datetime', nullable: true)]
    private ?DateTime $added = null;

    #[ORM\OneToOne(targetEntity: UserBlacklistData::class, mappedBy: 'blacklist', cascade: ['persist', 'remove'])]
    private ?UserBlacklistData $blacklistData = null;

    public function __construct(string $email)
    {
        $this->email = $email;
        $this->added = new DateTime();
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getAdded(): ?DateTime
    {
        return $this->added;
    }

    public function setAdded(?DateTime $added): self
    {
        $this->added = $added;
        return $this;
    }

    public function getBlacklistData(): ?UserBlacklistData
    {
        return $this->blacklistData;
    }

    public function setBlacklistData(?UserBlacklistData $data): self
    {
        $this->blacklistData = $data;
        if ($data && $data->getBlacklist() !== $this) {
            $data->setBlacklist($this);
        }
        return $this;
    }
}
