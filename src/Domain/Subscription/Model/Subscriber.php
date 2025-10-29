<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Model;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Common\Model\Interfaces\CreationDate;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Common\Model\Interfaces\Identity;
use PhpList\Core\Domain\Common\Model\Interfaces\ModificationDate;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;

/**
 * This class represents subscriber who can subscribe to multiple subscriber lists and can receive email messages from
 * campaigns for those subscriber lists.
 * @author Oliver Klee <oliver@phplist.com>
 * @author Tatevik Grigoryan <tatevik@phplist.com>
 */
#[ORM\Entity(repositoryClass: SubscriberRepository::class)]
#[ORM\Table(name: 'phplist_user_user')]
#[ORM\Index(name: 'idxuniqid', columns: ['uniqid'])]
#[ORM\Index(name: 'enteredindex', columns: ['entered'])]
#[ORM\Index(name: 'confidx', columns: ['confirmed'])]
#[ORM\Index(name: 'blidx', columns: ['blacklisted'])]
#[ORM\HasLifecycleCallbacks]
class Subscriber implements DomainModel, Identity, CreationDate, ModificationDate
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(name: 'entered', type: 'datetime', nullable: true)]
    protected ?DateTime $createdAt = null;

    #[ORM\Column(name: 'modified', type: 'datetime')]
    private ?DateTime $updatedAt = null;

    #[ORM\Column(unique: true)]
    private string $email = '';

    #[ORM\Column(type: 'boolean')]
    private bool $confirmed = false;

    #[ORM\Column(type: 'boolean')]
    private bool $blacklisted = false;

    #[ORM\Column(name: 'bouncecount', type: 'integer')]
    private int $bounceCount = 0;

    #[ORM\Column(name: 'uniqid', unique: true)]
    private string $uniqueId = '';

    #[ORM\Column(name: 'htmlemail', type: 'boolean')]
    private bool $htmlEmail = false;

    #[ORM\Column(type: 'boolean')]
    private bool $disabled = false;

    #[ORM\Column(name: 'extradata', type: 'text')]
    private ?string $extraData;

    #[ORM\OneToMany(
        targetEntity: Subscription::class,
        mappedBy: 'subscriber',
        cascade: ['remove'],
        orphanRemoval: true,
    )]
    private Collection $subscriptions;

    /**
     * @var Collection<int, SubscriberAttributeValue>
     */
    #[ORM\OneToMany(
        targetEntity: SubscriberAttributeValue::class,
        mappedBy: 'subscriber',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $attributes;

    public function __construct()
    {
        $this->subscriptions = new ArrayCollection();
        $this->attributes = new ArrayCollection();
        $this->extraData = '';
        $this->createdAt = new DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateUpdatedAt(): void
    {
        $this->updatedAt = new DateTime();
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed;
    }

    public function setConfirmed(bool $confirmed): self
    {
        $this->confirmed = $confirmed;

        return $this;
    }

    public function isBlacklisted(): bool
    {
        return $this->blacklisted;
    }

    public function setBlacklisted(bool $blacklisted): self
    {
        $this->blacklisted = $blacklisted;

        return $this;
    }

    public function getBounceCount(): int
    {
        return $this->bounceCount;
    }

    public function setBounceCount(int $bounceCount): self
    {
        $this->bounceCount = $bounceCount;

        return $this;
    }

    public function addToBounceCount(int $delta): self
    {
        $this->setBounceCount($this->getBounceCount() + $delta);

        return $this;
    }

    public function getUniqueId(): string
    {
        return $this->uniqueId;
    }

    public function setUniqueId(string $uniqueId): self
    {
        $this->uniqueId = $uniqueId;

        return $this;
    }

    #[ORM\PrePersist]
    public function generateUniqueId(): self
    {
        $this->setUniqueId(bin2hex(random_bytes(16)));
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

    public function hasHtmlEmail(): bool
    {
        return $this->htmlEmail;
    }

    public function setHtmlEmail(bool $htmlEmail): self
    {
        $this->htmlEmail = $htmlEmail;

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

    public function getExtraData(): string
    {
        return $this->extraData;
    }

    public function setExtraData(string $extraData): self
    {
        $this->extraData = $extraData;

        return $this;
    }

    /**
     * @return Collection<Subscription>
     */
    public function getSubscriptions(): Collection
    {
        return $this->subscriptions;
    }

    public function addSubscription(Subscription $subscription): self
    {
        if (!$this->subscriptions->contains($subscription)) {
            $this->subscriptions->add($subscription);
            $subscription->setSubscriber($this);
        }

        return $this;
    }

    public function removeSubscription(Subscription $subscription): self
    {
        if ($this->subscriptions->removeElement($subscription)) {
            $subscription->setSubscriber(null);
        }

        return $this;
    }

    public function getSubscribedLists(): Collection
    {
        $result = new ArrayCollection();
        foreach ($this->subscriptions as $subscription) {
            $result->add($subscription->getSubscriberList());
        }

        return $result;
    }

    public function getAttributes(): Collection
    {
        return $this->attributes;
    }

    public function addAttribute(SubscriberAttributeValue $attribute): self
    {
        if (!$this->attributes->contains($attribute)) {
            $this->attributes[] = $attribute;
        }

        return $this;
    }

    public function removeAttribute(SubscriberAttributeValue $attribute): self
    {
        $this->attributes->removeElement($attribute);
        return $this;
    }
}
