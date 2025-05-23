<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Model;

use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Subscription\Repository\SubscriberPageDataRepository;

#[ORM\Entity(repositoryClass: SubscriberPageDataRepository::class)]
#[ORM\Table(name: 'phplist_subscribepage_data')]
class SubscribePageData implements DomainModel
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'integer')]
    private int $id;

    #[ORM\Id]
    #[ORM\Column(name: 'name', type: 'string', length: 100)]
    private string $name;

    #[ORM\Column(name: 'data', type: 'text', nullable: true)]
    private ?string $data = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getData(): ?string
    {
        return $this->data;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function setData(?string $data): self
    {
        $this->data = $data;
        return $this;
    }
}
