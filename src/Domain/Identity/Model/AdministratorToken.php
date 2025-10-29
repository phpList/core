<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Model;

use DateTime;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Persistence\Proxy;
use PhpList\Core\Domain\Common\Model\Interfaces\CreationDate;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Common\Model\Interfaces\Identity;
use PhpList\Core\Domain\Identity\Repository\AdministratorTokenRepository;
use Symfony\Component\Serializer\Annotation\SerializedName;

/**
 * This class represents an API authentication token for an administrator.
 * @author Oliver Klee <oliver@phplist.com>
 * @author Tateik Grigoryan <tatevik@phplist.com>
 */
#[ORM\Entity(repositoryClass: AdministratorTokenRepository::class)]
#[ORM\Table(name: 'phplist_admintoken')]
#[ORM\HasLifecycleCallbacks]
class AdministratorToken implements DomainModel, Identity, CreationDate
{
    public const DEFAULT_EXPIRY = '+1 hour';

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(name: 'entered', type: 'integer')]
    protected int $createdAt = 0;

    #[ORM\Column(name: 'expires', type: 'datetime')]
    #[SerializedName('expiry_date')]
    private ?DateTime $expiry = null;

    #[ORM\Column(name: 'value')]
    #[SerializedName('key')]
    private string $key = '';

    #[ORM\ManyToOne(targetEntity: Administrator::class)]
    #[ORM\JoinColumn(name: 'adminid', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Administrator $administrator;

    public function __construct(Administrator $administrator)
    {
        $this->generateExpiry();
        $this->generateKey();
        $this->administrator = $administrator;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): ?DateTime
    {
        if ($this->createdAt === 0) {
            return null;
        }

        $date = new DateTime();
        $date->setTimestamp($this->createdAt);
        $date->setTimezone(new DateTimeZone('UTC'));
        return $date;
    }

    #[ORM\PrePersist]
    public function updateCreatedAt(): DomainModel
    {
        $this->createdAt = (new DateTime())->getTimestamp();
        return $this;
    }

    public function getExpiry(): DateTime
    {
        return $this->expiry;
    }

    private function setExpiry(DateTime $expiry): void
    {
        $this->expiry = $expiry;
    }

    public function generateExpiry(): self
    {
        $this->setExpiry(new DateTime(static::DEFAULT_EXPIRY));

        return $this;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): self
    {
        $this->key = $key;

        return $this;
    }

    public function generateKey(): self
    {
        $key = md5(random_bytes(256));
        $this->setKey($key);

        return $this;
    }

    public function getAdministrator(): Administrator
    {
        return $this->administrator;
    }
}
