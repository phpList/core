<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Messaging;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Subscription\Subscription;
use PhpList\Core\Domain\Repository\Messaging\SubscriberListRepository;
use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Serializer\Annotation\SerializedName;
use PhpList\Core\Domain\Model\Identity\Administrator;
use PhpList\Core\Domain\Model\Interfaces\CreationDate;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Model\Interfaces\Identity;
use PhpList\Core\Domain\Model\Interfaces\ModificationDate;
use PhpList\Core\Domain\Model\Traits\CreationDateTrait;
use PhpList\Core\Domain\Model\Traits\IdentityTrait;
use PhpList\Core\Domain\Model\Traits\ModificationDateTrait;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;

/**
 * This class represents an administrator who can log to the system, is allowed to administer
 * selected lists (as the owner), send campaigns to these lists and edit subscribers.
 * @author Oliver Klee <oliver@phplist.com>
 */
#[ORM\Entity(repositoryClass: SubscriberListRepository::class)]
#[ORM\Table(name: 'phplist_list')]
#[ORM\Index(name: 'nameidx', columns: ['name'])]
#[ORM\Index(name: 'listorderidx', columns: ['listorder'])]
#[ORM\HasLifecycleCallbacks]
class SubscriberList implements DomainModel, Identity, CreationDate, ModificationDate
{
    use IdentityTrait;
    use CreationDateTrait;
    use ModificationDateTrait;

    #[ORM\Column]
    #[SerializedName('name')]
    #[Groups(['SubscriberList'])]
    private string $name = '';

    #[ORM\Column]
    #[SerializedName('description')]
    #[Groups(['SubscriberList'])]
    private string $description = '';

    #[ORM\Column(name: 'entered', type: 'datetime', nullable: true)]
    #[SerializedName('creation_date')]
    #[Groups(['SubscriberList'])]
    protected ?DateTime $creationDate = null;

    #[ORM\Column(name: 'modified', type: 'datetime')]
    #[Ignore]
    protected ?DateTime $modificationDate = null;

    #[ORM\Column(name: 'listorder', type: 'integer', nullable: true)]
    #[SerializedName('list_position')]
    #[Groups(['SubscriberList'])]
    private ?int $listPosition;

    #[ORM\Column(name: 'prefix')]
    #[SerializedName('subject_prefix')]
    #[Groups(['SubscriberList'])]
    private ?string $subjectPrefix;

    #[ORM\Column(name: 'active', type: 'boolean')]
    #[SerializedName('public')]
    #[Groups(['SubscriberList'])]
    private bool $public;

    #[ORM\Column]
    #[SerializedName('category')]
    #[Groups(['SubscriberList'])]
    private string $category;

    #[ORM\ManyToOne(targetEntity: 'PhpList\Core\Domain\Model\Identity\Administrator')]
    #[ORM\JoinColumn(name: 'owner')]
    #[Ignore]
    private ?Administrator $owner = null;

    #[ORM\OneToMany(
        targetEntity: 'PhpList\Core\Domain\Model\Subscription\Subscription',
        mappedBy: 'subscriberList',
        cascade: ['remove'],
        orphanRemoval: true,
    )]
    #[MaxDepth(1)]
    private Collection $subscriptions;

    public function __construct()
    {
        $this->subscriptions = new ArrayCollection();
        $this->listPosition = 0;
        $this->subjectPrefix = '';
        $this->category = '';
        $this->public = false;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getListPosition(): ?int
    {
        return $this->listPosition;
    }

    public function setListPosition(int $listPosition): void
    {
        $this->listPosition = $listPosition;
    }

    public function getSubjectPrefix(): ?string
    {
        return $this->subjectPrefix;
    }

    public function setSubjectPrefix(string $subjectPrefix): void
    {
        $this->subjectPrefix = $subjectPrefix;
    }

    public function isPublic(): bool
    {
        return $this->public ?? false;
    }

    public function setPublic(bool $public): void
    {
        $this->public = $public;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): void
    {
        $this->category = $category;
    }

    public function getOwner(): ?Administrator
    {
        return $this->owner;
    }

    public function setOwner(Administrator $owner): void
    {
        $this->owner = $owner;
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
}
