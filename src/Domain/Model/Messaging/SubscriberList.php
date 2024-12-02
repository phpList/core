<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Messaging;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
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
#[ORM\Entity(repositoryClass: "PhpList\Core\Domain\Repository\Messaging\SubscriberListRepository")]
#[ORM\Table(name: "phplist_list")]
#[ORM\HasLifecycleCallbacks]
class SubscriberList implements DomainModel, Identity, CreationDate, ModificationDate
{
    use IdentityTrait;
    use CreationDateTrait;
    use ModificationDateTrait;

    #[ORM\Column]
    #[SerializedName("name")]
    #[Groups(['SubscriberList'])]
    private string $name = '';

    #[ORM\Column]
    #[SerializedName("description")]
    #[Groups(['SubscriberList'])]
    private string $description = '';

    #[ORM\Column(name: "entered", type: "datetime", nullable: true)]
    #[SerializedName("creation_date")]
    #[Groups(['SubscriberList'])]
    protected ?DateTime $creationDate = null;

    #[ORM\Column(name: "modified", type: "datetime")]
    #[Ignore]
    protected ?DateTime $modificationDate = null;

    #[ORM\Column(name: "listorder", type: "integer")]
    #[SerializedName("list_position")]
    #[Groups(['SubscriberList'])]
    private ?int $listPosition;

    #[ORM\Column(name: "prefix")]
    #[SerializedName("subject_prefix")]
    #[Groups(['SubscriberList'])]
    private ?string $subjectPrefix;

    #[ORM\Column(name: "active", type: "boolean")]
    #[SerializedName("public")]
    private bool $public = false;

    #[ORM\Column]
    #[SerializedName("category")]
    private string $category = '';

    #[ORM\ManyToOne(targetEntity: "PhpList\Core\Domain\Model\Identity\Administrator")]
    #[ORM\JoinColumn(name: "owner")]
    #[Ignore]
    private ?Administrator $owner = null;

    #[ORM\OneToMany(
        targetEntity: "PhpList\Core\Domain\Model\Subscription\Subscription",
        mappedBy: "subscriberList",
        cascade: ["remove"]
    )]
    #[MaxDepth(1)]
    private Collection $subscriptions;

    #[ORM\ManyToMany(
        targetEntity: "PhpList\Core\Domain\Model\Subscription\Subscriber",
        inversedBy: "subscribedLists",
        fetch: "EXTRA_LAZY"
    )]
    #[ORM\JoinTable(
        name: "phplist_listuser",
        joinColumns: [new ORM\JoinColumn(name: "listid")],
        inverseJoinColumns: [new ORM\JoinColumn(name: "userid")]
    )]
    #[MaxDepth(1)]
    private Collection $subscribers;

    public function __construct()
    {
        $this->subscriptions = new ArrayCollection();
        $this->subscribers = new ArrayCollection();
        $this->listPosition = 0;
        $this->subjectPrefix = '';
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

    public function getListPosition(): int
    {
        return $this->listPosition;
    }

    public function setListPosition(int $listPosition): void
    {
        $this->listPosition = $listPosition;
    }

    public function getSubjectPrefix(): string
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

    public function setSubscriptions(Collection $subscriptions): void
    {
        $this->subscriptions = $subscriptions;
    }

    public function getSubscribers(): Collection
    {
        return $this->subscribers;
    }

    public function setSubscribers(Collection $subscribers): void
    {
        $this->subscribers = $subscribers;
    }
}
