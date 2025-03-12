<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Messaging\Message;

use Doctrine\ORM\Mapping as ORM;
use DateTime;

#[ORM\Embeddable]
class MessageOptions
{
    #[ORM\Column(name: 'fromfield', type: "string", length: 255, nullable: false, options: ["default" => ""])]
    private string $fromField;

    #[ORM\Column(name: 'tofield', type: "string", length: 255, nullable: false, options: ["default" => ""])]
    private string $toField;

    #[ORM\Column(name: 'replyto', type: "string", length: 255, nullable: false, options: ["default" => ""])]
    private string $replyTo;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?DateTime $embargo;

    #[ORM\Column(name: "userselection", type: "text", nullable: true)]
    private ?string $userSelection;

    #[ORM\Column(type: "integer", nullable: true)]
    private ?int $template;

    #[ORM\Column(name: "sendstart", type: "datetime", nullable: true)]
    private ?DateTime $sendStart;

    #[ORM\Column(name: "rsstemplate", type: "string", length: 100, nullable: true)]
    private ?string $rssTemplate;

    #[ORM\Column(type: "integer", nullable: true)]
    private ?int $owner;

    public function __construct(
        string $fromField = "",
        string $toField = "",
        string $replyTo = "",
        ?DateTime $embargo = null,
        ?string $userSelection = null,
        ?int $template = null,
        ?DateTime $sendStart = null,
        ?string $rssTemplate = null,
        ?int $owner = null
    ) {
        $this->fromField = $fromField;
        $this->toField = $toField;
        $this->replyTo = $replyTo;
        $this->embargo = $embargo;
        $this->userSelection = $userSelection;
        $this->template = $template;
        $this->sendStart = $sendStart;
        $this->rssTemplate = $rssTemplate;
        $this->owner = $owner;
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

    public function getEmbargo(): ?DateTime
    {
        return $this->embargo;
    }

    public function getUserSelection(): ?string
    {
        return $this->userSelection;
    }

    public function getTemplate(): ?int
    {
        return $this->template;
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

