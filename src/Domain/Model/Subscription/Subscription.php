<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Domain\Model\Subscription;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Proxy\Proxy;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use PhpList\PhpList4\Domain\Model\Interfaces\CreationDate;
use PhpList\PhpList4\Domain\Model\Interfaces\DomainModel;
use PhpList\PhpList4\Domain\Model\Interfaces\ModificationDate;
use PhpList\PhpList4\Domain\Model\Messaging\SubscriberList;
use PhpList\PhpList4\Domain\Model\Traits\CreationDateTrait;
use PhpList\PhpList4\Domain\Model\Traits\ModificationDateTrait;

/**
 * This class represents asubscriber who can subscribe to multiple subscriber lists and can receive email messages from
 * campaigns for those subscriber lists.
 *
 * @Entity(repositoryClass="PhpList\PhpList4\Domain\Repository\Subscription\SubscriptionRepository")
 * @Table(name="phplist_listuser")
 * @HasLifecycleCallbacks
 * @ExclusionPolicy("all")
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class Subscription implements DomainModel, CreationDate, ModificationDate
{
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
     * @var Subscriber|Proxy|null
     * @Id
     * @ManyToOne(targetEntity="PhpList\PhpList4\Domain\Model\Subscription\Subscriber", inversedBy="subscriptions")
     * @JoinColumn(name="userid")
     */
    private $subscriber = null;

    /**
     * @var SubscriberList|Proxy|null
     * @Id
     * @ManyToOne(targetEntity="PhpList\PhpList4\Domain\Model\Messaging\SubscriberList", inversedBy="subscriptions")
     * @JoinColumn(name="listid")
     */
    private $subscriberList = null;

    /**
     * @return Subscriber|Proxy|null
     */
    public function getSubscriber()
    {
        return $this->subscriber;
    }

    /**
     * @param Subscriber $subscriber
     *
     * @return void
     */
    public function setSubscriber(Subscriber $subscriber)
    {
        $this->subscriber = $subscriber;
    }

    /**
     * @return SubscriberList|Proxy|null
     */
    public function getSubscriberList()
    {
        return $this->subscriberList;
    }

    /**
     * @param SubscriberList $subscriberList
     *
     * @return void
     */
    public function setSubscriberList(SubscriberList $subscriberList)
    {
        $this->subscriberList = $subscriberList;
    }
}
