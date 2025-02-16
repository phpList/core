<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Subscription;

use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;

#[ORM\Entity]
#[ORM\Table(name: "phplist_user_user_attribute")]
#[ORM\Index(name: "attindex", columns: ["attributeid"])]
#[ORM\Index(name: "attuserid", columns: ["userid", "attributeid"])]
#[ORM\Index(name: "userindex", columns: ["userid"])]
class SubscriberAttribute implements DomainModel
{
    #[ORM\Id]
    #[ORM\Column(name: "attributeid", type: "integer", nullable: false)]
    private int $id;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Subscriber::class)]
    #[ORM\JoinColumn(name: "userid", referencedColumnName: "id", nullable: false, onDelete: "CASCADE")]
    private Subscriber $subscriber;

    #[ORM\Column(name: "value", type: "text", nullable: true)]
    private ?string $value = null;

    public function __construct(int $id, Subscriber $subscriber)
    {
        $this->id = $id;
        $this->subscriber = $subscriber;
    }

    public function getId(): int
    {
        return $this->id;
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
