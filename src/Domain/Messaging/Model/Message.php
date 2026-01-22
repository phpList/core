<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Common\Model\Interfaces\Identity;
use PhpList\Core\Domain\Common\Model\Interfaces\ModificationDate;
use PhpList\Core\Domain\Common\Model\Interfaces\OwnableInterface;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Messaging\Model\Message\MessageContent;
use PhpList\Core\Domain\Messaging\Model\Message\MessageFormat;
use PhpList\Core\Domain\Messaging\Model\Message\MessageMetadata;
use PhpList\Core\Domain\Messaging\Model\Message\MessageOptions;
use PhpList\Core\Domain\Messaging\Model\Message\MessageSchedule;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: 'phplist_message')]
#[ORM\Index(name: 'phplist_message_uuididx', columns: ['uuid'])]
#[ORM\HasLifecycleCallbacks]
class Message implements DomainModel, Identity, ModificationDate, OwnableInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(name: 'modified', type: 'datetime', nullable: false)]
    private DateTime $updatedAt;

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

    #[ORM\OneToMany(targetEntity: ListMessage::class, mappedBy: 'message')]
    private Collection $listMessages;

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
        $this->listMessages = new ArrayCollection();
        $this->updatedAt = new DateTime();
        $this->metadata->setEntered(new DateTime());
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function touchUpdatedTimestamp(): void
    {
        $this->updatedAt = new DateTime;
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

    public function setUuid(string $uuid): self
    {
        $this->uuid = $uuid;
        return $this;
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

    public function getListMessages(): Collection
    {
        return $this->listMessages;
    }

    public function incrementSentCount(OutputFormat $sentAs): void
    {
        match ($sentAs) {
            OutputFormat::Html => $this->format->incrementAsHtml(),
            OutputFormat::Text => $this->format->incrementAsText(),
            OutputFormat::Pdf => $this->format->incrementAsPdf(),
            OutputFormat::TextAndHtml => $this->format->incrementAsTextAndHtml(),
            OutputFormat::TextAndPdf => $this->format->incrementAsTextAndPdf(),
        };
    }
}
