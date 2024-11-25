<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Identity;

use DateTime;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Persistence\Proxy;
use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Serializer\Annotation\SerializedName;
use PhpList\Core\Domain\Model\Interfaces\CreationDate;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Model\Interfaces\Identity;
use PhpList\Core\Domain\Model\Traits\IdentityTrait;

/**
 * This class represents an API authentication token for an administrator.
 */
#[ORM\Entity(repositoryClass: "PhpList\Core\Domain\Repository\Identity\AdministratorTokenRepository")]
#[ORM\Table(name: "phplist_admintoken")]
#[ORM\HasLifecycleCallbacks]
class AdministratorToken implements DomainModel, Identity, CreationDate
{
    use IdentityTrait;

    public const DEFAULT_EXPIRY = '+1 hour';

    #[ORM\Column(name: "entered", type: "integer")]
    #[Ignore]
    protected int $creationDate = 0;

    #[ORM\Column(name: "expires", type: "datetime")]
    #[SerializedName("expiry_date")]
    private ?DateTime $expiry = null;

    #[ORM\Column(name: "value")]
    #[SerializedName("key")]
    private string $key = '';

    #[ORM\ManyToOne(targetEntity: "PhpList\Core\Domain\Model\Identity\Administrator")]
    #[ORM\JoinColumn(name: "adminid")]
    #[Ignore]
    private ?Administrator $administrator = null;

    public function __construct()
    {
        $this->setExpiry(new DateTime());
    }

    public function getCreationDate(): ?DateTime
    {
        if ($this->creationDate === 0) {
            return null;
        }

        $date = new DateTime();
        $date->setTimestamp($this->creationDate);
        $date->setTimezone(new DateTimeZone('UTC'));
        return $date;
    }

    private function setCreationDate(DateTime $creationDate): void
    {
        $this->creationDate = $creationDate->getTimestamp();
    }

    #[ORM\PrePersist]
    public function updateCreationDate(): void
    {
        $this->setCreationDate(new DateTime());
    }

    public function getExpiry(): DateTime
    {
        return $this->expiry;
    }

    private function setExpiry(DateTime $expiry): void
    {
        $this->expiry = $expiry;
    }

    public function generateExpiry(): void
    {
        $this->setExpiry(new DateTime(static::DEFAULT_EXPIRY));
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): void
    {
        $this->key = $key;
    }

    public function generateKey(): void
    {
        $key = md5(random_bytes(256));
        $this->setKey($key);
    }

    public function getAdministrator(): Administrator|Proxy|null
    {
        return $this->administrator;
    }

    public function setAdministrator(Administrator $administrator): void
    {
        $this->administrator = $administrator;
    }
}
