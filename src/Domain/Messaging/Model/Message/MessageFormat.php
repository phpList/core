<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model\Message;

use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Common\Model\Interfaces\EmbeddableInterface;

#[ORM\Embeddable]
class MessageFormat implements EmbeddableInterface
{
    #[ORM\Column(name: 'htmlformatted', type: 'boolean')]
    private bool $htmlFormatted = false;

    #[ORM\Column(name: 'sendformat', type: 'string', length: 20, nullable: true)]
    private ?string $sendFormat = null;

    #[ORM\Column(name: 'astext', type: 'integer')]
    private int $asText = 0;

    #[ORM\Column(name: 'ashtml', type: 'integer')]
    private int $asHtml = 0;

    #[ORM\Column(name: 'aspdf', type: 'integer')]
    private int $asPdf = 0;

    #[ORM\Column(name: 'astextandhtml', type: 'integer')]
    private int $asTextAndHtml = 0;

    #[ORM\Column(name: 'astextandpdf', type: 'integer')]
    private int $asTextAndPdf = 0;

    public const FORMAT_TEXT = 'text';
    public const FORMAT_HTML = 'html';
    public const FORMAT_PDF = 'pdf';

    public function __construct(
        bool $htmlFormatted,
        ?string $sendFormat,
    ) {
        $this->htmlFormatted = $htmlFormatted;
        $this->sendFormat = $sendFormat;
    }

    public function isHtmlFormatted(): bool
    {
        return $this->htmlFormatted;
    }

    public function setHtmlFormatted(bool $htmlFormatted): self
    {
        $this->htmlFormatted = $htmlFormatted;
        return $this;
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

    public function incrementAsText(): void
    {
        $this->asText++;
    }

    public function incrementAsHtml(): void
    {
        $this->asHtml++;
    }

    public function incrementAsTextAndHtml(): void
    {
        $this->asTextAndHtml++;
    }

    public function incrementAsPdf(): void
    {
        $this->asPdf++;
    }

    public function incrementAsTextAndPdf(): void
    {
        $this->asTextAndPdf++;
    }

    public function getFormatOptions(): array
    {
        return array_values(array_filter([
            $this->asText ? self::FORMAT_TEXT : null,
            $this->asHtml ? self::FORMAT_HTML : null,
            $this->asPdf ? self::FORMAT_PDF : null,
        ]));
    }
}
