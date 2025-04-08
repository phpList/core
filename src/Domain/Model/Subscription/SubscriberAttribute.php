<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Subscription;

use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;

#[ORM\Entity]
#[ORM\Table(name: 'phplist_user_user_attribute')]
#[ORM\Index(name: 'attindex', columns: ['attributeid'])]
#[ORM\Index(name: 'attuserid', columns: ['userid', 'attributeid'])]
#[ORM\Index(name: 'userindex', columns: ['userid'])]
class SubscriberAttribute implements DomainModel
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: SubscriberAttributeDefinition::class)]
    #[ORM\JoinColumn(name: 'attributeid', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private SubscriberAttributeDefinition $attributeDefinition;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Subscriber::class)]
    #[ORM\JoinColumn(name: 'userid', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Subscriber $subscriber;

    #[ORM\Column(name: 'value', type: 'text', nullable: true)]
    private ?string $value = null;

    public function __construct(SubscriberAttributeDefinition $attributeDefinition, Subscriber $subscriber)
    {
        $this->attributeDefinition = $attributeDefinition;
        $this->subscriber = $subscriber;
    }

    public function getAttributeDefinition(): SubscriberAttributeDefinition
    {
        return $this->attributeDefinition;
    }

    public function getSubscriber(): Subscriber
    {
        return $this->subscriber;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): self
    {
        $this->value = $value;
        return $this;
    }
}
