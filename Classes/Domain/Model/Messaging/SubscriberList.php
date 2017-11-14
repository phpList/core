<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Domain\Model\Messaging;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Proxy\Proxy;
use PhpList\PhpList4\Domain\Model\Identity\Administrator;
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
 * @Entity(repositoryClass="PhpList\PhpList4\Domain\Repository\Messaging\SubscriberListRepository")
 * @Table(name="phplist_list")
 * @HasLifecycleCallbacks
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class SubscriberList implements Identity, CreationDate, ModificationDate
{
    use IdentityTrait;
    use CreationDateTrait;
    use ModificationDateTrait;

    /**
     * @var string
     * @Column
     */
    private $name = '';

    /**
     * @var string
     * @Column
     */
    private $description = '';

    /**
     * @var \DateTime|null
     * @Column(type="datetime", nullable=true, name="entered")
     */
    protected $creationDate = null;

    /**
     * @var \DateTime|null
     * @Column(type="datetime", nullable=true, name="modified")
     */
    protected $modificationDate = null;

    /**
     * @var int
     * @Column(type="integer", name="listorder")
     */
    private $listPosition = 0;

    /**
     * @var string
     * @Column(name="prefix")
     */
    private $subjectPrefix = '';

    /**
     * @var bool
     * @Column(type="boolean")
     */
    private $active = false;

    /**
     * @var string
     * @Column
     */
    private $category = '';

    /**
     * @var Administrator
     * @ManyToOne(targetEntity="PhpList\PhpList4\Domain\Model\Identity\Administrator")
     * @JoinColumn(name="owner")
     */
    private $owner = null;

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return void
     */
    public function setName(string $name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     *
     * @return void
     */
    public function setDescription(string $description)
    {
        $this->description = $description;
    }

    /**
     * @return int
     */
    public function getListPosition(): int
    {
        return $this->listPosition;
    }

    /**
     * @param int $listPosition
     *
     * @return void
     */
    public function setListPosition(int $listPosition)
    {
        $this->listPosition = $listPosition;
    }

    /**
     * @return string
     */
    public function getSubjectPrefix(): string
    {
        return $this->subjectPrefix;
    }

    /**
     * @param string $subjectPrefix
     *
     * @return void
     */
    public function setSubjectPrefix(string $subjectPrefix)
    {
        $this->subjectPrefix = $subjectPrefix;
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * @param bool $active
     *
     * @return void
     */
    public function setActive(bool $active)
    {
        $this->active = $active;
    }

    /**
     * @return string
     */
    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * @param string $category
     *
     * @return void
     */
    public function setCategory(string $category)
    {
        $this->category = $category;
    }

    /**
     * @return Administrator|Proxy|null
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * @param Administrator $owner
     *
     * @return void
     */
    public function setOwner(Administrator $owner)
    {
        $this->owner = $owner;
    }
}
