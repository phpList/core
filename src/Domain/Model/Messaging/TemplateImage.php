<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Messaging;

use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Model\Interfaces\Identity;
use PhpList\Core\Domain\Repository\Messaging\TemplateImageRepository;

#[ORM\Entity(repositoryClass: TemplateImageRepository::class)]
#[ORM\Table(name: 'phplist_templateimage')]
#[ORM\Index(name: 'templateidx', columns: ['template'])]
class TemplateImage implements DomainModel, Identity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Template::class, inversedBy: 'images')]
    #[ORM\JoinColumn(name: 'template', referencedColumnName: 'id', nullable: false)]
    private Template $template;

    #[ORM\Column(name: 'mimetype', type: 'string', length: 100, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(name: 'filename', type: 'string', length: 100, nullable: true)]
    private ?string $filename = null;

    #[ORM\Column(name: 'data', type: 'blob', nullable: true)]
    private mixed $data = null;

    #[ORM\Column(name: 'width', type: 'integer', nullable: true)]
    private ?int $width = null;

    #[ORM\Column(name: 'height', type: 'integer', nullable: true)]
    private ?int $height = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTemplate(): ?Template
    {
        return $this->template;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function getData(): ?string
    {
        if (is_resource($this->data)) {
            rewind($this->data);
            return stream_get_contents($this->data);
        }

        return $this->data;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setTemplate(Template $template): self
    {
        $this->template = $template;
        return $this;
    }

    public function setMimeType(?string $mimeType): self
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function setFilename(?string $filename): self
    {
        $this->filename = $filename;
        return $this;
    }

    public function setData(?string $data): self
    {
        $this->data = $data !== null ? fopen('data://text/plain,' . $data, 'r') : null;
        return $this;
    }

    public function setWidth(?int $width): self
    {
        $this->width = $width;
        return $this;
    }

    public function setHeight(?int $height): self
    {
        $this->height = $height;
        return $this;
    }
}
