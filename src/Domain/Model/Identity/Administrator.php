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

    public function setNameLc(string $nameLc): void
    {
        $this->namelc = $nameLc;
    }

    public function getNameLc(): string
    {
        return $this->namelc;
    }

    public function setPrivileges(string $privileges): void
    {
        $this->privileges = $privileges;
    }

    public function getPrivileges(): string
    {
        return $this->privileges;
    }
}
