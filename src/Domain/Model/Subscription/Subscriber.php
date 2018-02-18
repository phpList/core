<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Domain\Model\Subscription;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\JoinTable;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\PrePersist;
use Doctrine\ORM\Mapping\Table;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use PhpList\PhpList4\Domain\Model\Interfaces\CreationDate;
use PhpList\PhpList4\Domain\Model\Interfaces\Identity;
use PhpList\PhpList4\Domain\Model\Interfaces\ModificationDate;
use PhpList\PhpList4\Domain\Model\Traits\CreationDateTrait;
use PhpList\PhpList4\Domain\Model\Traits\IdentityTrait;
use PhpList\PhpList4\Domain\Model\Traits\ModificationDateTrait;

/**
 * This class represents asubscriber who can subscribe to multiple subscriber lists and can receive email messages from
 * campaigns for those subscriber lists.
 *
 * @Entity(repositoryClass="PhpList\PhpList4\Domain\Repository\Subscription\SubscriberRepository")
 * @Table(name="phplist_user_user")
 * @HasLifecycleCallbacks
 * @ExclusionPolicy("all")
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class Subscriber implements Identity, CreationDate, ModificationDate
{
    use IdentityTrait;
    use CreationDateTrait;
    use ModificationDateTrait;

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
     * @var string
     * @Column(unique=true)
     * @Expose
     */
    private $email = '';

    /**
     * @var bool
     * @Column(type="boolean")
     * @Expose
     */
    private $confirmed = false;

    /**
     * @var bool
     * @Column(type="boolean")
     * @Expose
     */
    private $blacklisted = false;

    /**
     * @var int
     * @Column(type="integer", name="bouncecount")
     * @Expose
     */
    private $bounceCount = 0;

    /**
     * Note: The uniqueness of this column will not be enforced as long as we use the old DB schema,
     * not the Doctrine-generated one.
     *
     * @var string
     * @Column(name="uniqid", unique=true)
     * @Expose
     */
    private $uniqueId = '';

    /**
     * @var bool
     * @Column(type="boolean", name="htmlemail")
     * @Expose
     */
    private $htmlEmail = false;

    /**
     * @var bool
     * @Column(type="boolean")
     * @Expose
     */
    private $disabled = false;

    /**
     * @var Collection
     * @OneToMany(
     *     targetEntity="PhpList\PhpList4\Domain\Model\Subscription\Subscription",
     *     mappedBy="subscriber",
     *     cascade={"remove"}
     *  )
     */
    private $subscriptions = null;

    /**
     * @var Collection
     * @ManyToMany(targetEntity="PhpList\PhpList4\Domain\Model\Messaging\SubscriberList", inversedBy="subscribers")
     * @JoinTable(name="phplist_listuser",
     *            joinColumns={@JoinColumn(name="userid")},
     *            inverseJoinColumns={@JoinColumn(name="listid")}
     * )
     */
    private $subscribedLists = null;

    /**
     * The constructor.
     */
    public function __construct()
    {
        $this->subscriptions = new ArrayCollection();
        $this->subscribedLists = new ArrayCollection();
    }

    /**
     * @return bool
     */
    public function isConfirmed(): bool
    {
        return $this->confirmed;
    }

    /**
     * @param bool $confirmed
     *
     * @return void
     */
    public function setConfirmed(bool $confirmed)
    {
        $this->confirmed = $confirmed;
    }

    /**
     * @return bool
     */
    public function isBlacklisted(): bool
    {
        return $this->blacklisted;
    }

    /**
     * @param bool $blacklisted
     *
     * @return void
     */
    public function setBlacklisted(bool $blacklisted)
    {
        $this->blacklisted = $blacklisted;
    }

    /**
     * @return int
     */
    public function getBounceCount(): int
    {
        return $this->bounceCount;
    }

    /**
     * @param int $bounceCount
     *
     * @return void
     */
    public function setBounceCount(int $bounceCount)
    {
        $this->bounceCount = $bounceCount;
    }

    /**
     * @param int $delta the number of bounces to add to the bounce count
     *
     * @return void
     */
    public function addToBounceCount(int $delta)
    {
        $this->setBounceCount($this->getBounceCount() + $delta);
    }

    /**
     * @return string
     */
    public function getUniqueId(): string
    {
        return $this->uniqueId;
    }

    /**
     * @param string $uniqueId
     *
     * @return void
     */
    public function setUniqueId(string $uniqueId)
    {
        $this->uniqueId = $uniqueId;
    }

    /**
     * Generates and sets a (new) random unique ID.
     *
     * @PrePersist
     *
     * @return void
     */
    public function generateUniqueId()
    {
        $this->setUniqueId(bin2hex(random_bytes(16)));
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @param string $email
     *
     * @return void
     */
    public function setEmail(string $email)
    {
        $this->email = $email;
    }

    /**
     * @return bool
     */
    public function hasHtmlEmail(): bool
    {
        return $this->htmlEmail;
    }

    /**
     * @param bool $htmlEmail
     *
     * @return void
     */
    public function setHtmlEmail(bool $htmlEmail)
    {
        $this->htmlEmail = $htmlEmail;
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
    public function getSubscribedLists(): Collection
    {
        return $this->subscribedLists;
    }

    /**
     * @param Collection $subscribedLists
     *
     * @return void
     */
    public function setSubscribedLists(Collection $subscribedLists)
    {
        $this->subscribedLists = $subscribedLists;
    }
}
