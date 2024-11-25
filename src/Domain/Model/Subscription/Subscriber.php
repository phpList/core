<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Subscription;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Serializer\Annotation\SerializedName;
use PhpList\Core\Domain\Model\Interfaces\CreationDate;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Model\Interfaces\Identity;
use PhpList\Core\Domain\Model\Interfaces\ModificationDate;
use PhpList\Core\Domain\Model\Traits\CreationDateTrait;
use PhpList\Core\Domain\Model\Traits\IdentityTrait;
use PhpList\Core\Domain\Model\Traits\ModificationDateTrait;

/**
 * This class represents subscriber who can subscribe to multiple subscriber lists and can receive email messages from
 * campaigns for those subscriber lists.
 * @author Oliver Klee <oliver@phplist.com>
 */
#[ORM\Entity(repositoryClass: "PhpList\Core\Domain\Repository\Subscription\SubscriberRepository")]
#[ORM\Table(name: "phplist_user_user")]
#[ORM\HasLifecycleCallbacks]
class Subscriber implements DomainModel, Identity, CreationDate, ModificationDate
{
    use IdentityTrait;
    use CreationDateTrait;
    use ModificationDateTrait;

    #[ORM\Column(name: "entered", type: "datetime", nullable: true)]
    #[SerializedName("creation_date")]
    protected ?DateTime $creationDate = null;

    #[ORM\Column(name: "modified", type: "datetime")]
    #[Ignore]
    protected ?DateTime $modificationDate = null;

    #[ORM\Column(unique: true)]
    #[SerializedName("email")]
    private string $email = '';

    #[ORM\Column(type: "boolean")]
    #[SerializedName("confirmed")]
    private bool $confirmed = false;

    #[ORM\Column(type: "boolean")]
    #[SerializedName("blacklisted")]
    private bool $blacklisted = false;

    #[ORM\Column(name: "bouncecount", type: "integer")]
    #[SerializedName("bounce_count")]
    private int $bounceCount = 0;

    #[ORM\Column(name: "uniqid", unique: true)]
    #[SerializedName("unique_id")]
    private string $uniqueId = '';

    #[ORM\Column(name: "htmlemail", type: "boolean")]
    #[SerializedName("html_email")]
    private bool $htmlEmail = false;

    #[ORM\Column(type: "boolean")]
    #[SerializedName("disabled")]
    private bool $disabled = false;

    #[ORM\Column(name: "extradata", type: "text")]
    #[SerializedName("extra_data")]
    private string $extraData = '';

    #[ORM\OneToMany(
        mappedBy: "subscriber",
        targetEntity: "PhpList\Core\Domain\Model\Subscription\Subscription",
        cascade: ["remove"]
    )]
    private Collection $subscriptions;

    #[ORM\ManyToMany(
        targetEntity: "PhpList\Core\Domain\Model\Messaging\SubscriberList",
        inversedBy: "subscribers"
    )]
    #[ORM\JoinTable(
        name: "phplist_listuser",
        joinColumns: [new ORM\JoinColumn(name: "userid")],
        inverseJoinColumns: [new ORM\JoinColumn(name: "listid")]
    )]
    private Collection $subscribedLists;

    public function __construct()
    {
        $this->subscriptions = new ArrayCollection();
        $this->subscribedLists = new ArrayCollection();
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed;
    }

    public function setConfirmed(bool $confirmed): void
    {
        $this->confirmed = $confirmed;
    }

    public function isBlacklisted(): bool
    {
        return $this->blacklisted;
    }

    public function setBlacklisted(bool $blacklisted): void
    {
        $this->blacklisted = $blacklisted;
    }

    public function getBounceCount(): int
    {
        return $this->bounceCount;
    }

    public function setBounceCount(int $bounceCount): void
    {
        $this->bounceCount = $bounceCount;
    }

    public function addToBounceCount(int $delta): void
    {
        $this->setBounceCount($this->getBounceCount() + $delta);
    }

    public function getUniqueId(): string
    {
        return $this->uniqueId;
    }

    public function setUniqueId(string $uniqueId): void
    {
        $this->uniqueId = $uniqueId;
    }

    #[ORM\PrePersist]
    public function generateUniqueId(): void
    {
        $this->setUniqueId(bin2hex(random_bytes(16)));
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function hasHtmlEmail(): bool
    {
        return $this->htmlEmail;
    }

    public function setHtmlEmail(bool $htmlEmail): void
    {
        $this->htmlEmail = $htmlEmail;
    }

    public function isDisabled(): bool
    {
        return $this->disabled;
    }

    public function setDisabled(bool $disabled): void
    {
        $this->disabled = $disabled;
    }

    public function getExtraData(): string
    {
        return $this->extraData;
    }

    public function setExtraData(string $extraData): void
    {
        $this->extraData = $extraData;
    }

    public function getSubscriptions(): Collection
    {
        return $this->subscriptions;
    }

    public function setSubscriptions(Collection $subscriptions): void
    {
        $this->subscriptions = $subscriptions;
    }

    public function getSubscribedLists(): Collection
    {
        return $this->subscribedLists;
    }

    public function setSubscribedLists(Collection $subscribedLists): void
    {
        $this->subscribedLists = $subscribedLists;
    }
}
