<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Messaging\Message;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class MessageFormat
{
    #[ORM\Column(type: "boolean", options: ["default" => false])]
    private bool $htmlFormatted = false;

    #[ORM\Column(name: "sendformat", type: "string", length: 20, nullable: true)]
    private ?string $sendFormat = null;

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

    public function __construct(bool $htmlFormatted, ?string $sendFormat)
    {
        $this->htmlFormatted = $htmlFormatted;
        $this->sendFormat = $sendFormat;
    }

    public function isHtmlFormatted(): bool
    {
        return $this->htmlFormatted;
    }

    public function getSendFormat(): ?string
    {
        return $this->sendFormat;
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

    public function setSendFormat(?string $sendFormat): self
    {
        $this->sendFormat = $sendFormat;
        return $this;
    }
}

