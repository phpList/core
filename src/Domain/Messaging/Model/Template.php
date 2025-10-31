<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Common\Model\Interfaces\Identity;
use PhpList\Core\Domain\Messaging\Repository\TemplateRepository;

#[ORM\Entity(repositoryClass: TemplateRepository::class)]
#[ORM\Table(name: 'phplist_template')]
#[ORM\UniqueConstraint(name: 'phplist_template_title', columns: ['title'])]
class Template implements DomainModel, Identity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(name: 'title', type: 'string', length: 255, unique: true)]
    private string $title;

    #[ORM\Column(name: 'template', type: 'blob', nullable: true)]
    private mixed $content;

    #[ORM\Column(name: 'template_text', type: 'blob', nullable: true)]
    private mixed $text;

    #[ORM\Column(name: 'listorder', type: 'integer', nullable: true)]
    private ?int $listOrder = null;

    #[ORM\OneToMany(
        targetEntity: TemplateImage::class,
        mappedBy: 'template',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $images;

    public function __construct(string $title)
    {
        $this->title = $title;
        $this->images = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getContent(): ?string
    {
        if (is_resource($this->content)) {
            rewind($this->content);
            return stream_get_contents($this->content);
        }

        return $this->content;
    }

    public function getText(): ?string
    {
        if (is_resource($this->text)) {
            rewind($this->text);
            return stream_get_contents($this->text);
        }

        return $this->text;
    }

    public function getListOrder(): ?int
    {
        return $this->listOrder;
    }

    public function getImages(): Collection
    {
        return $this->images;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content !== null ? fopen('data://text/plain,' . $content, 'r') : null;
        return $this;
    }


    public function setText(?string $text): self
    {
        $this->text = $text !== null ? fopen('data://text/plain,' . $text, 'r') : null;
        return $this;
    }

    public function setListOrder(?int $listOrder): self
    {
        $this->listOrder = $listOrder;
        return $this;
    }
}
