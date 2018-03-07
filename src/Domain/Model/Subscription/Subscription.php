<?php
declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Subscription;

use Doctrine\ORM\Mapping;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Proxy\Proxy;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use PhpList\Core\Domain\Model\Interfaces\CreationDate;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Model\Interfaces\ModificationDate;
use PhpList\Core\Domain\Model\Messaging\SubscriberList;
use PhpList\Core\Domain\Model\Traits\CreationDateTrait;
use PhpList\Core\Domain\Model\Traits\ModificationDateTrait;

/**
 * This class represents asubscriber who can subscribe to multiple subscriber lists and can receive email messages from
 * campaigns for those subscriber lists.
 *
 * @Mapping\Entity(repositoryClass="PhpList\Core\Domain\Repository\Subscription\SubscriptionRepository")
 * @Mapping\Table(name="phplist_listuser")
 * @Mapping\HasLifecycleCallbacks
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
     * @Mapping\Id
     * @Mapping\ManyToOne(
     *     targetEntity="PhpList\Core\Domain\Model\Subscription\Subscriber",
     *     inversedBy="subscriptions"
     * )
     * @Mapping\JoinColumn(name="userid")
     */
    private $subscriber = null;

    /**
     * @var SubscriberList|Proxy|null
     * @Mapping\Id
     * @Mapping\ManyToOne(
     *     targetEntity="PhpList\Core\Domain\Model\Messaging\SubscriberList",
     *     inversedBy="subscriptions"
     * )
     * @Mapping\JoinColumn(name="listid")
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
