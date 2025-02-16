<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Messaging;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Model\Interfaces\Identity;
use PhpList\Core\Domain\Model\Traits\IdentityTrait;

#[ORM\Entity]
#[ORM\Table(name: "phplist_template")]
#[ORM\UniqueConstraint(name: "title", columns: ["title"])]
class Template implements DomainModel, Identity
{
    use IdentityTrait;

    #[ORM\Column(name: "title", type: "string", length: 255, unique: true)]
    private string $title;

    #[ORM\Column(name: "template", type: "blob", nullable: true)]
    private ?string $template = null;

    #[ORM\Column(name: "template_text", type: "blob", nullable: true)]
    private ?string $templateText = null;

    #[ORM\Column(name: "listorder", type: "integer", nullable: true)]
    private ?int $listOrder = null;

    #[ORM\OneToMany(targetEntity: TemplateImage::class, mappedBy: "template", cascade: ["persist", "remove"], orphanRemoval: true)]
    private Collection $images;

    public function __construct()
    {
        $this->images = new ArrayCollection();
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    public function getTemplateText(): ?string
    {
        return $this->templateText;
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

    public function setTemplate(?string $template): self
    {
        $this->template = $template;
        return $this;
    }

    public function setTemplateText(?string $templateText): self
    {
        $this->templateText = $templateText;
        return $this;
    }

    public function setListOrder(?int $listOrder): self
    {
        $this->listOrder = $listOrder;
        return $this;
    }
}
