<?php
declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Messaging;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Proxy\Proxy;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use PhpList\Core\Domain\Model\Identity\Administrator;
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
 *
 * @Mapping\Entity(repositoryClass="PhpList\Core\Domain\Repository\Messaging\SubscriberListRepository")
 * @Mapping\Table(name="phplist_list")
 * @Mapping\HasLifecycleCallbacks
 * @ExclusionPolicy("all")
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class SubscriberList implements DomainModel, Identity, CreationDate, ModificationDate
{
    use IdentityTrait;
    use CreationDateTrait;
    use ModificationDateTrait;

    /**
     * @var string
     * @Column
     * @Expose
     */
    private $name = '';

    /**
     * @var string
     * @Column
     * @Expose
     */
    private $description = '';

    /**
     * @var \DateTime|null
     * @Column(type="datetime", nullable=true, name="entered")
     * @Expose
     */
    protected $creationDate = null;

    /**
     * @var \DateTime|null
     * @Column(type="datetime", name="modified")
     */
    protected $modificationDate = null;

    /**
     * @var int
     * @Column(type="integer", name="listorder")
     * @Expose
     */
    private $listPosition = 0;

    /**
     * @var string
     * @Column(name="prefix")
     * @Expose
     */
    private $subjectPrefix = '';

    /**
     * @var bool
     * @Column(type="boolean", name="active")
     * @Expose
     */
    private $public = false;

    /**
     * @var string
     * @Column
     * @Expose
     */
    private $category = '';

    /**
     * @var Administrator
     * @Mapping\ManyToOne(targetEntity="PhpList\Core\Domain\Model\Identity\Administrator")
     * @Mapping\JoinColumn(name="owner")
     */
    private $owner = null;

    /**
     * @var Collection
     * @Mapping\OneToMany(
     *     targetEntity="PhpList\Core\Domain\Model\Subscription\Subscription",
     *     mappedBy="subscriberList",
     *     cascade={"remove"}
     * )
     */
    private $subscriptions = null;

    /**
     * @var Collection
     * @Mapping\ManyToMany(
     *     targetEntity="PhpList\Core\Domain\Model\Subscription\Subscriber",
     *     inversedBy="subscribedLists",
     *     fetch="EXTRA_LAZY"
     * )
     * @Mapping\JoinTable(name="phplist_listuser",
     *     joinColumns={@Mapping\JoinColumn(name="listid")},
     *     inverseJoinColumns={@Mapping\JoinColumn(name="userid")}
     * )
     */
    private $subscribers = null;

    /**
     * The constructor.
     */
    public function __construct()
    {
        $this->subscriptions = new ArrayCollection();
        $this->subscribers = new ArrayCollection();
    }

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
    public function isPublic(): bool
    {
        return $this->public;
    }

    /**
     * @param bool $public
     *
     * @return void
     */
    public function setPublic(bool $public)
    {
        $this->public = $public;
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

    /**
     * @return Collection
     */
    public function getSubscriptions(): Collection
    {
        return $this->subscriptions;
    }

    /**
     * @param Collection $subscriptions
     *
     * @return void
     */
    public function setSubscriptions(Collection $subscriptions)
    {
        $this->subscriptions = $subscriptions;
    }

    /**
     * @return Collection
     */
    public function getSubscribers(): Collection
    {
        return $this->subscribers;
    }

    /**
     * @param Collection $subscribers
     *
     * @return void
     */
    public function setSubscribers(Collection $subscribers)
    {
        $this->subscribers = $subscribers;
    }
}
