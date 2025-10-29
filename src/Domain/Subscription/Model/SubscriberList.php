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
use PhpList\Core\Domain\Common\Model\Interfaces\OwnableInterface;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Messaging\Model\ListMessage;
use PhpList\Core\Domain\Subscription\Repository\SubscriberListRepository;
use Symfony\Component\Serializer\Attribute\MaxDepth;

/**
 * This class represents an administrator who can log to the system, is allowed to administer
 * selected lists (as the owner), send campaigns to these lists and edit subscribers.
 * @author Oliver Klee <oliver@phplist.com>
 * @author Tatevik Grigoryan <tatevik@phplist.com>
 */
#[ORM\Entity(repositoryClass: SubscriberListRepository::class)]
#[ORM\Table(name: 'phplist_list')]
#[ORM\Index(name: 'nameidx', columns: ['name'])]
#[ORM\Index(name: 'listorderidx', columns: ['listorder'])]
#[ORM\HasLifecycleCallbacks]
class SubscriberList implements DomainModel, Identity, CreationDate, ModificationDate, OwnableInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column]
    private string $name = '';

    #[ORM\Column(name: 'rssfeed', type: 'string', length: 255, nullable: true)]
    private ?string $rssFeed = null;

    #[ORM\Column]
    private ?string $description;

    #[ORM\Column(name: 'entered', type: 'datetime', nullable: true)]
    protected ?DateTime $createdAt = null;

    #[ORM\Column(name: 'modified', type: 'datetime')]
    private ?DateTime $updatedAt = null;

    #[ORM\Column(name: 'listorder', type: 'integer', nullable: true)]
    private ?int $listPosition;

    #[ORM\Column(name: 'prefix', length: 10, nullable: true)]
    private ?string $subjectPrefix;

    #[ORM\Column(name: 'active', type: 'boolean')]
    private bool $public;

    #[ORM\Column]
    private string $category;

    #[ORM\ManyToOne(targetEntity: Administrator::class)]
    #[ORM\JoinColumn(name: 'owner')]
    private ?Administrator $owner = null;

    #[ORM\OneToMany(
        targetEntity: Subscription::class,
        mappedBy: 'subscriberList',
        cascade: ['remove'],
        orphanRemoval: true,
    )]
    #[MaxDepth(1)]
    private Collection $subscriptions;

    #[ORM\OneToMany(targetEntity: ListMessage::class, mappedBy: 'subscriberList')]
    private Collection $listMessages;

    public function __construct()
    {
        $this->subscriptions = new ArrayCollection();
        $this->listMessages = new ArrayCollection();
        $this->createdAt = new DateTime();
        $this->listPosition = 0;
        $this->subjectPrefix = '';
        $this->category = '';
        $this->public = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRssFeed(): ?string
    {
        return $this->rssFeed;
    }

    public function setRssFeed(?string $rssFeed): self
    {
        $this->rssFeed = $rssFeed;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getListPosition(): ?int
    {
        return $this->listPosition;
    }

    public function setListPosition(int $listPosition): self
    {
        $this->listPosition = $listPosition;
        return $this;
    }

    public function getSubjectPrefix(): ?string
    {
        return $this->subjectPrefix;
    }

    public function setSubjectPrefix(string $subjectPrefix): self
    {
        $this->subjectPrefix = $subjectPrefix;

        return $this;
    }

    public function isPublic(): bool
    {
        return $this->public ?? false;
    }

    public function setPublic(bool $public): self
    {
        $this->public = $public;
        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getOwner(): ?Administrator
    {
        return $this->owner;
    }

    public function setOwner(Administrator $owner): self
    {
        $this->owner = $owner;
        return $this;
    }

    public function getSubscriptions(): Collection
    {
        return $this->subscriptions;
    }

    public function addSubscription(Subscription $subscription): self
    {
        if (!$this->subscriptions->contains($subscription)) {
            $this->subscriptions->add($subscription);
            $subscription->setSubscriberList($this);
        }

        return $this;
    }

    public function removeSubscription(Subscription $subscription): self
    {
        if ($this->subscriptions->removeElement($subscription)) {
            $subscription->setSubscriberList(null);
        }

        return $this;
    }

    public function getSubscribers(): Collection
    {
        $result = new ArrayCollection();
        foreach ($this->subscriptions as $subscription) {
            $result->add($subscription->getSubscriber());
        }

        return $result;
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

    public function getListMessages(): Collection
    {
        return $this->listMessages;
    }
}
