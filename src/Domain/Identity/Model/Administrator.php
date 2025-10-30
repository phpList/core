<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Model;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use PhpList\Core\Domain\Common\Model\Interfaces\CreationDate;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Common\Model\Interfaces\Identity;
use PhpList\Core\Domain\Common\Model\Interfaces\ModificationDate;
use PhpList\Core\Domain\Common\Model\Interfaces\OwnableInterface;
use PhpList\Core\Domain\Identity\Repository\AdministratorRepository;

/**
 * This class represents an administrator who can log to the system, is allowed to administer
 * selected lists (as the owner), send campaigns to these lists and edit subscribers.
 *
 * @author Oliver Klee <oliver@phplist.com>
 * @author Tatevik Grigoryan <tatevik@phplist.com>
 */
#[ORM\Entity(repositoryClass: AdministratorRepository::class)]
#[ORM\Table(name: 'phplist_admin')]
#[ORM\UniqueConstraint(name: 'phplist_admin_loginnameidx', columns: ['loginname'])]
#[ORM\HasLifecycleCallbacks]
class Administrator implements DomainModel, Identity, CreationDate, ModificationDate
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(name: 'created', type: 'datetime', nullable: true)]
    protected DateTime $createdAt;

    #[ORM\Column(name: 'modified', type: 'datetime', nullable: false)]
    private DateTime $updatedAt;

    #[ORM\Column(name: 'loginname', type: 'string', length: 66, nullable: false)]
    private string $loginName;

    #[ORM\Column(name: 'namelc', type: 'string', length: 255, nullable: true)]
    private ?string $namelc = null;

    #[ORM\Column(name: 'email', type: 'string', length: 255, nullable: false)]
    private string $email;

    #[ORM\Column(name: 'modifiedby', type: 'string', length: 66, nullable: true)]
    private ?string $modifiedBy = null;

    #[ORM\Column(name: 'password', type: 'string', length: 255, nullable: true)]
    private ?string $passwordHash = null;

    #[ORM\Column(name: 'passwordchanged', type: 'date', nullable: true)]
    private ?DateTime $passwordChangeDate = null;

    #[ORM\Column(name: 'disabled', type: 'boolean', nullable: false)]
    private bool $disabled = false;

    #[ORM\Column(name: 'superuser', type: 'boolean', nullable: false)]
    private bool $superUser = false;

    #[ORM\Column(name: 'privileges', type: 'text', nullable: true)]
    private ?string $privileges = null;

    public function __construct()
    {
        $this->createdAt = new DateTime();
        $this->updatedAt = new DateTime();
        $this->email = '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    public function getLoginName(): string
    {
        return $this->loginName;
    }

    public function setLoginName(string $loginName): self
    {
        $this->loginName = $loginName;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getNameLc(): ?string
    {
        return $this->namelc;
    }

    public function setNameLc(?string $nameLc): self
    {
        $this->namelc = $nameLc;
        return $this;
    }

    public function getPasswordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(?string $passwordHash): self
    {
        $this->passwordHash = $passwordHash;
        $this->passwordChangeDate = $passwordHash !== null ? new DateTime() : null;
        return $this;
    }

    public function getPasswordChangeDate(): ?DateTime
    {
        return $this->passwordChangeDate;
    }

    public function isDisabled(): bool
    {
        return $this->disabled;
    }

    public function setDisabled(bool $disabled): self
    {
        $this->disabled = $disabled;
        return $this;
    }

    public function isSuperUser(): bool
    {
        return $this->superUser;
    }

    public function setSuperUser(bool $superUser): self
    {
        $this->superUser = $superUser;
        return $this;
    }

    public function setPrivileges(Privileges $privileges): self
    {
        $this->privileges = $privileges->toSerialized();
        return $this;
    }

    /**
     * @throws InvalidArgumentException
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function setPrivilegesFromArray(array $privilegesData): void
    {
        $privileges = new Privileges();
        foreach ($privilegesData as $key => $value) {
            $flag = PrivilegeFlag::tryFrom($key);
            if (!$flag) {
                throw new InvalidArgumentException('Unknown privilege key: ' . $key);
            }
            $privileges = $value ? $privileges->grant($flag) : $privileges->revoke($flag);
        }
        $this->setPrivileges($privileges);
    }

    /** @SuppressWarnings(PHPMD.StaticAccess) */
    public function getPrivileges(): Privileges
    {
        return Privileges::fromSerialized($this->privileges);
    }

    public function setModifiedBy(?string $modifiedBy): self
    {
        $this->modifiedBy = $modifiedBy;
        return $this;
    }

    public function getModifiedBy(): ?string
    {
        return $this->modifiedBy;
    }

    public function owns(OwnableInterface $resource): bool
    {
        if ($this->getId() === null) {
            return false;
        }

        return $resource->getOwner()->getId() === $this->getId();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAt(): void
    {
        $this->updatedAt = new DateTime();
    }
}
