<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Messaging;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Identity\Administrator;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Model\Interfaces\Identity;
use PhpList\Core\Domain\Model\Interfaces\ModificationDate;
use PhpList\Core\Domain\Model\Messaging\Message\MessageContent;
use PhpList\Core\Domain\Model\Messaging\Message\MessageFormat;
use PhpList\Core\Domain\Model\Messaging\Message\MessageMetadata;
use PhpList\Core\Domain\Model\Messaging\Message\MessageOptions;
use PhpList\Core\Domain\Model\Messaging\Message\MessageSchedule;
use PhpList\Core\Domain\Repository\Messaging\MessageRepository;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: 'phplist_message')]
#[ORM\Index(name: 'uuididx', columns: ['uuid'])]
#[ORM\HasLifecycleCallbacks]
class Message implements DomainModel, Identity, ModificationDate
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(name: 'modified', type: 'datetime')]
    private ?DateTime $updatedAt = null;

    #[ORM\Embedded(class: MessageFormat::class, columnPrefix: false)]
    private MessageFormat $format;

    #[ORM\Embedded(class: MessageSchedule::class, columnPrefix: false)]
    private MessageSchedule $schedule;

    #[ORM\Embedded(class: MessageMetadata::class, columnPrefix: false)]
    private MessageMetadata $metadata;

    #[ORM\Embedded(class: MessageContent::class, columnPrefix: false)]
    private MessageContent $content;

    #[ORM\Embedded(class: MessageOptions::class, columnPrefix: false)]
    private MessageOptions $options;

    #[ORM\Column(type: 'string', length: 36, nullable: true, options: ['default' => ''])]
    private ?string $uuid = '';

    #[ORM\ManyToOne(targetEntity: Administrator::class)]
    #[ORM\JoinColumn(name: 'owner', referencedColumnName: 'id', nullable: true)]
    private ?Administrator $owner;

    #[ORM\ManyToOne(targetEntity: Template::class)]
    #[ORM\JoinColumn(name: 'template', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Template $template = null;

    public function __construct(
        MessageFormat $format,
        MessageSchedule $schedule,
        MessageMetadata $metadata,
        MessageContent $content,
        MessageOptions $options,
        ?Administrator $owner,
        ?Template $template = null,
    ) {
        $this->format = $format;
        $this->schedule = $schedule;
        $this->metadata = $metadata;
        $this->content = $content;
        $this->options = $options;
        $this->uuid = bin2hex(random_bytes(18));
        $this->owner = $owner;
        $this->template = $template;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateUpdatedAt(): DomainModel
    {
        $this->updatedAt = new DateTime();

        return $this;
    }

    public function getFormat(): MessageFormat
    {
        return $this->format;
    }

    public function getSchedule(): MessageSchedule
    {
        return $this->schedule;
    }

    public function getMetadata(): MessageMetadata
    {
        return $this->metadata;
    }

    public function getContent(): MessageContent
    {
        return $this->content;
    }

    public function getOptions(): MessageOptions
    {
        return $this->options;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function getOwner(): ?Administrator
    {
        return $this->owner;
    }

    public function setOwner(?Administrator $owner): self
    {
        $this->owner = $owner;
        return $this;
    }

    public function getTemplate(): ?Template
    {
        return $this->template;
    }

    public function setTemplate(?Template $template): self
    {
        $this->template = $template;
        return $this;
    }

    public function setFormat(MessageFormat $format): self
    {
        $this->format = $format;
        return $this;
    }

    public function setSchedule(MessageSchedule $schedule): self
    {
        $this->schedule = $schedule;
        return $this;
    }

    public function setMetadata(MessageMetadata $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function setContent(MessageContent $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function setOptions(MessageOptions $options): self
    {
        $this->options = $options;
        return $this;
    }
}
