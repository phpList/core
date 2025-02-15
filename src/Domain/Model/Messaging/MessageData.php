<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Messaging;

use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;

#[ORM\Entity]
#[ORM\Table(name: "phplist_messagedata")]
class MessageData implements DomainModel
{
    #[ORM\Id]
    #[ORM\Column(name: "name", type: "string", length: 100)]
    private string $name;

    #[ORM\Id]
    #[ORM\Column(name: "id", type: "integer")]
    private int $id;

    #[ORM\Column(name: "data", type: "text", nullable: true, options: ["charset" => "utf8mb4"])]
    private ?string $data = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getData(): ?string
    {
        return $this->data;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function setData(?string $data): self
    {
        $this->data = $data;
        return $this;
    }
}
