<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Model;

use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Common\Model\Interfaces\Identity;
use PhpList\Core\Domain\Subscription\Repository\SubscriberPageRepository;

#[ORM\Entity(repositoryClass: SubscriberPageRepository::class)]
#[ORM\Table(name: 'phplist_subscribepage')]
class SubscribePage implements DomainModel, Identity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(name: 'title', type: 'string', length: 255)]
    private string $title;

    #[ORM\Column(name: 'active', type: 'boolean', options: ['default' => 0])]
    private bool $active = false;

    #[ORM\Column(name: 'owner', type: 'integer', nullable: true)]
    private ?int $owner = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getOwner(): ?int
    {
        return $this->owner;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;
        return $this;
    }

    public function setOwner(?int $owner): self
    {
        $this->owner = $owner;
        return $this;
    }
}
