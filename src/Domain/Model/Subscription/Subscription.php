<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Subscription;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Persistence\Proxy;
use PhpList\Core\Domain\Model\Interfaces\CreationDate;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Model\Interfaces\ModificationDate;
use PhpList\Core\Domain\Model\Traits\CreationDateTrait;
use PhpList\Core\Domain\Model\Traits\ModificationDateTrait;
use PhpList\Core\Domain\Repository\Subscription\SubscriptionRepository;
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
#[ORM\Index(name: 'userenteredidx', columns: ['userid', 'entered'])]
#[ORM\Index(name: 'userlistenteredidx', columns: ['userid', 'entered', 'listid'])]
#[ORM\Index(name: 'useridx', columns: ['userid'])]
#[ORM\Index(name: 'listidx', columns: ['listid'])]
#[ORM\HasLifecycleCallbacks]
class Subscription implements DomainModel, CreationDate, ModificationDate
{
    use CreationDateTrait;
    use ModificationDateTrait;

    #[ORM\Column(name: 'entered', type: 'datetime', nullable: true)]
    #[SerializedName('creation_date')]
    protected ?DateTime $creationDate = null;

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
}
