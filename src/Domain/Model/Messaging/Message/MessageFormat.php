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
    private int $asText;

    #[ORM\Column(name: 'ashtml', type: 'integer', options: ['default' => 0])]
    private int $asHtml;

    #[ORM\Column(name: 'aspdf', type: 'integer', options: ['default' => 0])]
    private int $asPdf;

    #[ORM\Column(name: 'astextandhtml', type: 'integer', options: ['default' => 0])]
    private int $asTextAndHtml;

    #[ORM\Column(name: 'astextandpdf', type: 'integer', options: ['default' => 0])]
    private int $asTextAndPdf;

    public function __construct(
        bool $htmlFormatted,
        string $sendFormat = null,
        int $asText = 0,
        int $asHtml = 0,
        int $asPdf = 0,
        int $asTextAndHtml = 0,
        int $asTextAndPdf = 0,
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
