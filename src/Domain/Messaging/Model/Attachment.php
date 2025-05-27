<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model;

use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Common\Model\Interfaces\Identity;
use PhpList\Core\Domain\Messaging\Repository\AttachmentRepository;

#[ORM\Entity(repositoryClass: AttachmentRepository::class)]
#[ORM\Table(name: 'phplist_attachment')]
class Attachment implements DomainModel, Identity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $filename;

    #[ORM\Column(name:'remotefile', type: 'string', length: 255, nullable: true)]
    private ?string $remoteFile;

    #[ORM\Column(name: 'mimetype', type: 'string', length: 255, nullable: true)]
    private ?string $mimeType;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $size;

    public function __construct(
        ?string $filename = null,
        ?string $remoteFile = null,
        ?string $mimeType = null,
        ?string $description = null,
        ?int $size = null
    ) {
        $this->filename = $filename;
        $this->remoteFile = $remoteFile;
        $this->mimeType = $mimeType;
        $this->description = $description;
        $this->size = $size;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(?string $filename): self
    {
        $this->filename = $filename;
        return $this;
    }

    public function getRemoteFile(): ?string
    {
        return $this->remoteFile;
    }

    public function setRemoteFile(?string $remoteFile): self
    {
        $this->remoteFile = $remoteFile;
        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): self
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(?int $size): self
    {
        $this->size = $size;
        return $this;
    }
}
