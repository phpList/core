<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Identity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\CreationDate;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Model\Interfaces\Identity;
use PhpList\Core\Domain\Model\Interfaces\ModificationDate;
use PhpList\Core\Domain\Model\Traits\CreationDateTrait;
use PhpList\Core\Domain\Model\Traits\IdentityTrait;
use PhpList\Core\Domain\Model\Traits\ModificationDateTrait;

/**
 * This class represents an administrator who can log to the system, is allowed to administer
 * selected lists (as the owner), send campaigns to these lists and edit subscribers.
 */
#[ORM\Entity(repositoryClass: "PhpList\Core\Domain\Repository\Identity\AdministratorRepository")]
#[ORM\Table(name: "phplist_admin")]
#[ORM\HasLifecycleCallbacks]
class Administrator implements DomainModel, Identity, CreationDate, ModificationDate
{
    use IdentityTrait;
    use CreationDateTrait;
    use ModificationDateTrait;

    #[ORM\Column(name: "loginname")]
    private string $loginName = '';

    #[ORM\Column(name: "email")]
    private string $emailAddress = '';

    #[ORM\Column(name: "created", type: "datetime")]
    protected ?DateTime $creationDate = null;

    #[ORM\Column(name: "modified", type: "datetime")]
    protected ?DateTime $modificationDate = null;

    #[ORM\Column(name: "password")]
    private string $passwordHash = '';

    #[ORM\Column(name: "passwordchanged", type: "date", nullable: true)]
    private ?DateTime $passwordChangeDate = null;

    #[ORM\Column(type: "boolean")]
    private bool $disabled = false;

    #[ORM\Column(name: "superuser", type: "boolean")]
    private bool $superUser = false;

    public function getLoginName(): string
    {
        return $this->loginName;
    }

    public function setLoginName(string $loginName): void
    {
        $this->loginName = $loginName;
    }

    public function getEmailAddress(): string
    {
        return $this->emailAddress;
    }

    public function setEmailAddress(string $emailAddress): void
    {
        $this->emailAddress = $emailAddress;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    /**
     * Sets the password hash and updates the password change date to now.
     */
    public function setPasswordHash(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
        $this->setPasswordChangeDate(new DateTime());
    }

    public function getPasswordChangeDate(): ?DateTime
    {
        return $this->passwordChangeDate;
    }

    private function setPasswordChangeDate(DateTime $changeDate): void
    {
        $this->passwordChangeDate = $changeDate;
    }

    public function isDisabled(): bool
    {
        return $this->disabled;
    }

    public function setDisabled(bool $disabled): void
    {
        $this->disabled = $disabled;
    }

    public function isSuperUser(): bool
    {
        return $this->superUser;
    }

    public function setSuperUser(bool $superUser): void
    {
        $this->superUser = $superUser;
    }

    #[ORM\PrePersist]
    public function setCreationDate(): void
    {
        if ($this->creationDate === null) {
            $this->creationDate = new DateTime();
        }
    }
}
