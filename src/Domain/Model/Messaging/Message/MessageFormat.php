<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Messaging\Message;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class MessageFormat
{
    #[ORM\Column(name: 'htmlformatted', type: 'boolean', options: ['default' => false])]
    private bool $htmlFormatted = false;

    #[ORM\Column(name: 'sendformat', type: 'string', length: 20, nullable: true)]
    private ?string $sendFormat = null;

    #[ORM\Column(name: 'astext', type: 'integer', options: ['default' => 0])]
    private bool $asText;

    #[ORM\Column(name: 'ashtml', type: 'integer', options: ['default' => 0])]
    private bool $asHtml;

    #[ORM\Column(name: 'aspdf', type: 'integer', options: ['default' => 0])]
    private bool $asPdf;

    #[ORM\Column(name: 'astextandhtml', type: 'integer', options: ['default' => 0])]
    private bool $asTextAndHtml;

    #[ORM\Column(name: 'astextandpdf', type: 'integer', options: ['default' => 0])]
    private bool $asTextAndPdf;

    public function __construct(
        bool $htmlFormatted,
        string $sendFormat = null,
        bool $asText = false,
        bool $asHtml = false,
        bool $asPdf = false,
        bool $asTextAndHtml = false,
        bool $asTextAndPdf = false,
    ) {
        $this->htmlFormatted = $htmlFormatted;
        $this->sendFormat = $sendFormat;
        $this->asText = $asText;
        $this->asHtml = $asHtml;
        $this->asPdf = $asPdf;
        $this->asTextAndHtml = $asTextAndHtml;
        $this->asTextAndPdf = $asTextAndPdf;
    }

    public function isHtmlFormatted(): bool
    {
        return $this->htmlFormatted;
    }

    public function getSendFormat(): ?string
    {
        return $this->sendFormat;
    }

    public function isAsText(): bool
    {
        return $this->asText;
    }

    public function isAsHtml(): bool
    {
        return $this->asHtml;
    }

    public function isAsTextAndHtml(): bool
    {
        return $this->asTextAndHtml;
    }

    public function isAsPdf(): bool
    {
        return $this->asPdf;
    }

    public function isAsTextAndPdf(): bool
    {
        return $this->asTextAndPdf;
    }

    public function setSendFormat(?string $sendFormat): self
    {
        $this->sendFormat = $sendFormat;
        return $this;
    }

    public function setAsText(bool $asText): self
    {
        $this->asText = $asText;
        return $this;
    }

    public function setAsHtml(bool $asHtml): self
    {
        $this->asHtml = $asHtml;
        return $this;
    }

    public function setAsPdf(bool $asPdf): self
    {
        $this->asPdf = $asPdf;
        return $this;
    }

    public function setAsTextAndHtml(bool $asTextAndHtml): self
    {
        $this->asTextAndHtml = $asTextAndHtml;
        return $this;
    }

    public function setAsTextAndPdf(bool $asTextAndPdf): self
    {
        $this->asTextAndPdf = $asTextAndPdf;
        return $this;
    }
}
