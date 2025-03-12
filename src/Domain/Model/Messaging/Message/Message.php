<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Messaging\Message;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Model\Interfaces\Identity;
use PhpList\Core\Domain\Model\Interfaces\ModificationDate;
use PhpList\Core\Domain\Model\Traits\IdentityTrait;
use PhpList\Core\Domain\Model\Traits\ModificationDateTrait;
use PhpList\Core\Domain\Repository\Messaging\MessageRepository;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: "phplist_message")]
#[ORM\Index(name: "uuididx", columns: ["uuid"])]
#[ORM\HasLifecycleCallbacks]
class Message implements DomainModel, Identity, ModificationDate
{
    use IdentityTrait;
    use ModificationDateTrait;

    #[ORM\Embedded(class: "MessageFormat")]
    private MessageFormat $format;

    #[ORM\Embedded(class: "MessageSchedule")]
    private MessageSchedule $schedule;

    #[ORM\Embedded(class: "MessageMetadata")]
    private MessageMetadata $metadata;

    #[ORM\Embedded(class: "MessageContent")]
    private MessageContent $content;

    #[ORM\Embedded(class: "MessageOptions")]
    private MessageOptions $options;

    #[ORM\Column(type: "string", length: 36, nullable: true, options: ["default" => ""])]
    private ?string $uuid = '';

    public function __construct(
        MessageFormat $format,
        MessageSchedule $schedule,
        MessageMetadata $metadata,
        MessageContent $content,
        MessageOptions $options
    ) {
        $this->format = $format;
        $this->schedule = $schedule;
        $this->metadata = $metadata;
        $this->content = $content;
        $this->options = $options;
        $this->uuid = bin2hex(random_bytes(18));
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
}
