<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Model;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Persistence\Proxy;
use PhpList\Core\Domain\Common\Model\Interfaces\CreationDate;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Common\Model\Interfaces\ModificationDate;
use PhpList\Core\Domain\Subscription\Repository\SubscriptionRepository;
use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * This class represents subscriber who can subscribe to multiple subscriber lists and can receive email messages from
 * campaigns for those subscriber lists.
 * @author Oliver Klee <oliver@phplist.com>
 * @author Tatevik Grigoryan <tatevik@phplist.com>
 */
#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\Table(name: 'phplist_listuser')]
#[ORM\Index(name: 'phplist_listuser_userenteredidx', columns: ['userid', 'entered'])]
#[ORM\Index(name: 'phplist_listuser_userlistenteredidx', columns: ['userid', 'entered', 'listid'])]
#[ORM\Index(name: 'phplist_listuser_useridx', columns: ['userid'])]
#[ORM\Index(name: 'phplist_listuser_listidx', columns: ['listid'])]
#[ORM\HasLifecycleCallbacks]
class Subscription implements DomainModel, CreationDate, ModificationDate
{
    #[ORM\Column(name: 'entered', type: 'datetime', nullable: true)]
    #[SerializedName('creation_date')]
    protected ?DateTime $createdAt = null;

    #[ORM\Column(name: 'modified', type: 'datetime')]
    private ?DateTime $updatedAt = null;

    #[ORM\Id]
    #[ORM\ManyToOne(
        targetEntity: Subscriber::class,
        inversedBy: 'subscriptions'
    )]
    #[ORM\JoinColumn(name: 'userid')]
    #[SerializedName('subscriber')]
    private ?Subscriber $subscriber = null;

    #[ORM\Id]
    #[ORM\ManyToOne(
        targetEntity: SubscriberList::class,
        inversedBy: 'subscriptions'
    )]
    #[ORM\JoinColumn(name: 'listid', onDelete: 'CASCADE')]
    #[Ignore]
    #[Groups(['SubscriberListMembers'])]
    private ?SubscriberList $subscriberList = null;

    public function __construct()
    {
        $this->createdAt = new DateTime();
    }

    public function getSubscriber(): Subscriber|Proxy|null
    {
        return $this->subscriber;
    }

    public function setSubscriber(?Subscriber $subscriber): self
    {
        $this->subscriber = $subscriber;
        return $this;
    }

    public function getSubscriberList(): ?SubscriberList
    {
        return $this->subscriberList;
    }

    public function setSubscriberList(?SubscriberList $subscriberList): self
    {
        $this->subscriberList = $subscriberList;
        return $this;
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
}
