<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model;

use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Messaging\Repository\MessageDataRepository;

#[ORM\Entity(repositoryClass: MessageDataRepository::class)]
// utf8mb4 for the whole table (campaign data holds emoji etc.): a column-level charset differing from the
// table charset makes MySQL report the server's default collation for the column, which never matches.
#[ORM\Table(name: 'messagedata', options: ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci'])]
class MessageData implements DomainModel
{
    #[ORM\Id]
    #[ORM\Column(name: 'name', type: 'string', length: 100)]
    private string $name;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'integer')]
    private int $id;

    #[ORM\Column(name: 'data', type: 'text', nullable: true)]
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
