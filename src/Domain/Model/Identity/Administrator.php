<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Domain\Model\Identity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\Table;
use PhpList\PhpList4\Domain\Model\Interfaces\CreationDate;
use PhpList\PhpList4\Domain\Model\Interfaces\Identity;
use PhpList\PhpList4\Domain\Model\Interfaces\ModificationDate;
use PhpList\PhpList4\Domain\Model\Traits\CreationDateTrait;
use PhpList\PhpList4\Domain\Model\Traits\IdentityTrait;
use PhpList\PhpList4\Domain\Model\Traits\ModificationDateTrait;

/**
 * This class represents an administrator who can log to the system, is allowed to administer
 * selected lists (as the owner), send campaigns to these lists and edit subscribers.
 *
 * @Entity(repositoryClass="PhpList\PhpList4\Domain\Repository\Identity\AdministratorRepository")
 * @Table(name="phplist_admin")
 * @HasLifecycleCallbacks
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class Administrator implements Identity, CreationDate, ModificationDate
{
    use IdentityTrait;
    use CreationDateTrait;
    use ModificationDateTrait;

    /**
     * @var string
     * @Column(name="loginname")
     */
    private $loginName = '';

    /**
     * @var string
     * @Column(name="email")
     */
    private $emailAddress = '';

    /**
     * @var \DateTime|null
     * @Column(type="datetime", name="created")
     */
    protected $creationDate = null;

    /**
     * @var \DateTime|null
     * @Column(type="datetime", name="modified")
     */
    protected $modificationDate = null;

    /**
     * @var string
     * @Column(name="password")
     */
    private $passwordHash = '';

    /**
     * @var \DateTime|null
     * @Column(type="date", nullable=true, name="passwordchanged")
     */
    private $passwordChangeDate = null;

    /**
     * @var bool
     * @Column(type="boolean")
     */
    private $disabled = false;

    /**
     * @var bool
     * @Column(type="boolean", name="superuser")
     */
    private $superUser = false;

    /**
     * @return string
     */
    public function getLoginName(): string
    {
        return $this->loginName;
    }

    /**
     * @param string $loginName
     *
     * @return void
     */
    public function setLoginName(string $loginName)
    {
        $this->loginName = $loginName;
    }

    /**
     * @return string
     */
    public function getEmailAddress(): string
    {
        return $this->emailAddress;
    }

    /**
     * @param string $emailAddress
     *
     * @return void
     */
    public function setEmailAddress(string $emailAddress)
    {
        $this->emailAddress = $emailAddress;
    }

    /**
     * @return string
     */
    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    /**
     * Sets the password hash and updates the password change date to now.
     *
     * @param string $passwordHash
     *
     * @return void
     */
    public function setPasswordHash(string $passwordHash)
    {
        $this->passwordHash = $passwordHash;
        $this->setPasswordChangeDate(new \DateTime());
    }

    /**
     * @return \DateTime|null
     */
    public function getPasswordChangeDate()
    {
        return $this->passwordChangeDate;
    }

    /**
     * @param \DateTime $changeDate
     *
     * @return void
     */
    private function setPasswordChangeDate(\DateTime $changeDate)
    {
        $this->passwordChangeDate = $changeDate;
    }

    /**
     * @return bool
     */
    public function isDisabled(): bool
    {
        return $this->disabled;
    }

    /**
     * @param bool $disabled
     *
     * @return void
     */
    public function setDisabled(bool $disabled)
    {
        $this->disabled = $disabled;
    }

    /**
     * @return bool
     */
    public function isSuperUser(): bool
    {
        return $this->superUser;
    }

    /**
     * @param bool $superUser
     *
     * @return void
     */
    public function setSuperUser(bool $superUser)
    {
        $this->superUser = $superUser;
    }
}
