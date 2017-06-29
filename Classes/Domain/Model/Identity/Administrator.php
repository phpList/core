<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Domain\Model\Identity;

use PhpList\PhpList4\Domain\Model\Interfaces\Identity;
use PhpList\PhpList4\Domain\Model\Traits\IdentityTrait;

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
class Administrator implements Identity
{
    use IdentityTrait;

    /**
     * @var string
     * @Column(name="loginname")
     */
    private $loginName = '';

    /**
     * @var string
     * @Column(name="namelc")
     */
    private $normalizedLoginName = '';

    /**
     * @var string
     * @Column(name="email")
     */
    private $emailAddress = '';

    /**
     * @var \DateTime|null
     * @Column(type="datetime", nullable=true, name="created")
     */
    private $creationDate = null;

    /**
     * @var \DateTime|null
     * @Column(type="datetime", nullable=true, name="modified")
     */
    private $modificationDate = null;

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
    public function getNormalizedLoginName(): string
    {
        return $this->normalizedLoginName;
    }

    /**
     * @param string $normalizedLoginName
     *
     * @return void
     */
    public function setNormalizedLoginName(string $normalizedLoginName)
    {
        $this->normalizedLoginName = $normalizedLoginName;
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
     * @return \DateTime|null
     */
    public function getCreationDate()
    {
        return $this->creationDate;
    }

    /**
     * @param \DateTime $creationDate
     *
     * @return void
     */
    private function setCreationDate(\DateTime $creationDate)
    {
        $this->creationDate = $creationDate;
    }

    /**
     * Updates the creation date to now.
     *
     * @PrePersist
     *
     * @return void
     */
    public function updateCreationDate()
    {
        $this->setCreationDate(new \DateTime());
    }

    /**
     * @return \DateTime|null
     */
    public function getModificationDate()
    {
        return $this->modificationDate;
    }

    /**
     * @param \DateTime $modificationDate
     *
     * @return void
     */
    private function setModificationDate(\DateTime $modificationDate)
    {
        $this->modificationDate = $modificationDate;
    }

    /**
     * Updates the modification date to now.
     *
     * @PrePersist
     * @PreUpdate
     *
     * @return void
     */
    public function updateModificationDate()
    {
        $this->setModificationDate(new \DateTime());
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
}
