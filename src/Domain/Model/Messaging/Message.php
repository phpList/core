<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Messaging;

use Doctrine\ORM\Mapping as ORM;
use DateTime;
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

    #[ORM\Column(type: "string", length: 36, nullable: true, options: ["default" => ""])]
    private ?string $uuid = '';

    #[ORM\Column(type: "string", length: 255, nullable: false, options: ["default" => "(no subject)"])]
    private string $subject;

    #[ORM\Column(name: 'fromfield', type: "string", length: 255, nullable: false, options: ["default" => ""])]
    private string $fromField;

    #[ORM\Column(name: 'tofield', type: "string", length: 255, nullable: false, options: ["default" => ""])]
    private string $toField;

    #[ORM\Column(name: 'replyto', type: "string", length: 255, nullable: false, options: ["default" => ""])]
    private string $replyTo;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $message = null;

    #[ORM\Column(name: 'textmessage', type: "text", nullable: true)]
    private ?string $textMessage = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $footer = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?DateTime $entered = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?DateTime $embargo = null;

    #[ORM\Column(name: "repeatinterval", type: "integer", nullable: true, options: ["default" => 0])]
    private ?int $repeatInterval = 0;

    #[ORM\Column(name: "repeatuntil", type: "datetime", nullable: true)]
    private ?DateTime $repeatUntil = null;

    #[ORM\Column(name: "requeueinterval", type: "integer", nullable: true, options: ["default" => 0])]
    private ?int $requeueInterval = 0;

    #[ORM\Column(name: "requeueuntil", type: "datetime", nullable: true)]
    private ?DateTime $requeueUntil = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(name: "userselection", type: "text", nullable: true)]
    private ?string $userSelection = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?DateTime $sent = null;

    #[ORM\Column(name: "htmlformatted", type: "boolean", options: ["default" => false])]
    private bool $htmlFormatted = false;

    #[ORM\Column(name: "sendformat", type: "string", length: 20, nullable: true)]
    private ?string $sendFormat = null;

    #[ORM\Column(type: "integer", nullable: true)]
    private ?int $template = null;

    #[ORM\Column(type: "integer", options: ["unsigned" => true, "default" => 0])]
    private int $processed = 0;

    #[ORM\Column(name: "astext", type: "integer", options: ["default" => 0])]
    private int $asText = 0;

    #[ORM\Column(name: "ashtml", type: "integer", options: ["default" => 0])]
    private int $asHtml = 0;

    #[ORM\Column(name: "astextandhtml", type: "integer", options: ["default" => 0])]
    private int $asTextAndHtml = 0;

    #[ORM\Column(name: "aspdf", type: "integer", options: ["default" => 0])]
    private int $asPdf = 0;

    #[ORM\Column(name: "astextandpdf", type: "integer", options: ["default" => 0])]
    private int $asTextAndPdf = 0;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $viewed = 0;

    #[ORM\Column(name: "bouncecount", type: "integer", options: ["default" => 0])]
    private int $bounceCount = 0;

    #[ORM\Column(name: "sendstart", type: "datetime", nullable: true)]
    private ?DateTime $sendStart = null;

    #[ORM\Column(name: "rsstemplate", type: "string", length: 100, nullable: true)]
    private ?string $rssTemplate = null;

    #[ORM\Column(type: "integer", nullable: true)]
    private ?int $owner = null;

    public function __construct(
        string $subject = "(no subject)",
        string $fromField = "",
        string $toField = "",
        string $replyTo = "",
        ?string $message = null,
        ?string $textMessage = null,
        ?string $footer = null,
        ?DateTime $entered = null,
        ?DateTime $embargo = null,
        ?int $repeatInterval = 0,
        ?DateTime $repeatUntil = null,
        ?int $requeueInterval = 0,
        ?DateTime $requeueUntil = null,
        ?string $status = null,
        ?string $userSelection = null,
        ?DateTime $sent = null,
        bool $htmlFormatted = false,
        ?string $sendFormat = null,
        ?int $template = null,
        int $processed = 0,
        int $viewed = 0,
        int $bounceCount = 0,
        ?DateTime $sendStart = null,
        ?string $rssTemplate = null,
        ?int $owner = null
    ) {
        $this->uuid = bin2hex(random_bytes(18));
        $this->subject = $subject;
        $this->fromField = $fromField;
        $this->toField = $toField;
        $this->replyTo = $replyTo;
        $this->message = $message;
        $this->textMessage = $textMessage;
        $this->footer = $footer;
        $this->entered = $entered ?? new DateTime();
        $this->embargo = $embargo;
        $this->repeatInterval = $repeatInterval;
        $this->repeatUntil = $repeatUntil;
        $this->requeueInterval = $requeueInterval;
        $this->requeueUntil = $requeueUntil;
        $this->status = $status;
        $this->userSelection = $userSelection;
        $this->sent = $sent;
        $this->htmlFormatted = $htmlFormatted;
        $this->sendFormat = $sendFormat;
        $this->template = $template;
        $this->processed = $processed;
        $this->viewed = $viewed;
        $this->bounceCount = $bounceCount;
        $this->sendStart = $sendStart;
        $this->rssTemplate = $rssTemplate;
        $this->owner = $owner;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function getFromField(): string
    {
        return $this->fromField;
    }

    public function getToField(): string
    {
        return $this->toField;
    }

    public function getReplyToO(): string
    {
        return $this->replyTo;
    }

    public function getTextMessage(): ?string
    {
        return $this->textMessage;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getFooter(): ?string
    {
        return $this->footer;
    }

    public function getEntered(): ?DateTime
    {
        return $this->entered;
    }

    public function getEmbargo(): ?DateTime
    {
        return $this->embargo;
    }

    public function getRepeatInterval(): ?int
    {
        return $this->repeatInterval;
    }

    public function getRepeatUntil(): ?DateTime
    {
        return $this->repeatUntil;
    }

    public function getRequeueInterval(): ?int
    {
        return $this->requeueInterval;
    }

    public function getRequeueUntil(): ?DateTime
    {
        return $this->requeueUntil;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getSendFormat(): ?string
    {
        return $this->sendFormat;
    }

    public function setSendFormat(?string $sendFormat): self
    {
        $this->sendFormat = $sendFormat;
        return $this;
    }

    public function getProcessed(): int
    {
        return $this->processed;
    }

    public function setProcessed(int $processed): self
    {
        $this->processed = $processed;
        return $this;
    }

    public function getUserSelection(): ?string
    {
        return $this->userSelection;
    }

    public function getSent(): ?DateTime
    {
        return $this->sent;
    }

    public function isHtmlFormatted(): bool
    {
        return $this->htmlFormatted;
    }

    public function getTemplate(): ?int
    {
        return $this->template;
    }

    public function getAsText(): int
    {
        return $this->asText;
    }

    public function getAsHtml(): int
    {
        return $this->asHtml;
    }

    public function getAsTextAndHtml(): int
    {
        return $this->asTextAndHtml;
    }

    public function getAsPdf(): int
    {
        return $this->asPdf;
    }

    public function getAsTextAndPdf(): int
    {
        return $this->asTextAndPdf;
    }

    public function getViewed(): int
    {
        return $this->viewed;
    }

    public function getBounceCount(): int
    {
        return $this->bounceCount;
    }

    public function getSendStart(): ?DateTime
    {
        return $this->sendStart;
    }

    public function getRssTemplate(): ?string
    {
        return $this->rssTemplate;
    }

    public function getOwner(): ?int
    {
        return $this->owner;
    }
}
