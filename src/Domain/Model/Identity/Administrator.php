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
use PhpList\Core\Domain\Repository\Identity\AdministratorRepository;

/**
 * This class represents an administrator who can log to the system, is allowed to administer
 * selected lists (as the owner), send campaigns to these lists and edit subscribers.
 *
 * @author Oliver Klee <oliver@phplist.com>
 * @author Tatevik Grigoryan <tatevik@phplist.com>
 */
#[ORM\Entity(repositoryClass: AdministratorRepository::class)]
#[ORM\Table(name: 'phplist_admin')]
#[ORM\HasLifecycleCallbacks]
class Administrator implements DomainModel, Identity, CreationDate, ModificationDate
{
    use IdentityTrait;
    use CreationDateTrait;
    use ModificationDateTrait;

    #[ORM\Column(name: 'loginname')]
    private string $loginName;

    #[ORM\Column(name: 'namelc', nullable: true)]
    private string $namelc;

    #[ORM\Column(name: 'email')]
    private string $emailAddress;

    #[ORM\Column(name: 'created', type: 'datetime')]
    protected ?DateTime $creationDate = null;

    #[ORM\Column(name: 'modifiedby', type: 'string', length: 66, nullable: true)]
    protected ?string $modifiedBy;

    #[ORM\Column(name: 'password')]
    private string $passwordHash;

    #[ORM\Column(name: 'passwordchanged', type: 'date', nullable: true)]
    private ?DateTime $passwordChangeDate;

    #[ORM\Column(type: 'boolean')]
    private bool $disabled;

    #[ORM\Column(name: 'superuser', type: 'boolean')]
    private bool $superUser;

    #[ORM\Column(name: 'privileges', type: 'text', nullable: true)]
    private ?string $privileges;

    public function __construct()
    {
        $this->disabled = false;
        $this->superUser = false;
        $this->passwordChangeDate = null;
        $this->loginName = '';
        $this->passwordHash = '';
        $this->modificationDate = null;
        $this->emailAddress = '';
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

    public function getEmailAddress(): string
    {
        return $this->emailAddress;
    }

    public function setEmailAddress(string $emailAddress): self
    {
        $this->emailAddress = $emailAddress;

        return $this;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): self
    {
        $this->passwordHash = $passwordHash;
        $this->setPasswordChangeDate(new DateTime());

        return $this;
    }

    public function getPasswordChangeDate(): ?DateTime
    {
        return $this->passwordChangeDate;
    }

    private function setPasswordChangeDate(DateTime $changeDate): self
    {
        $this->passwordChangeDate = $changeDate;

        return $this;
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

    public function setNameLc(string $nameLc): self
    {
        $this->namelc = $nameLc;

        return $this;
    }

    public function getNameLc(): string
    {
        return $this->namelc;
    }

    public function setPrivileges(string $privileges): self
    {
        $this->privileges = $privileges;

        return $this;
    }

    public function getPrivileges(): string
    {
        return $this->privileges;
    }
}
