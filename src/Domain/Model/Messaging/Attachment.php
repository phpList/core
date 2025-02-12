<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Messaging;

use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Model\Interfaces\Identity;
use PhpList\Core\Domain\Model\Traits\IdentityTrait;
use PhpList\Core\Domain\Repository\Messaging\SubscriberAttachmentRepository;

#[ORM\Entity(repositoryClass: SubscriberAttachmentRepository::class)]
#[ORM\Table(name: 'phplist_attachment')]
class Attachment implements DomainModel, Identity
{
    use IdentityTrait;

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

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(?string $filename): void
    {
        $this->filename = $filename;
    }

    public function getRemoteFile(): ?string
    {
        return $this->remoteFile;
    }

    public function setRemoteFile(?string $remoteFile): void
    {
        $this->remoteFile = $remoteFile;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): void
    {
        $this->mimeType = $mimeType;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(?int $size): void
    {
        $this->size = $size;
    }
}
