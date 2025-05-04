<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Messaging\Message;

use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use PhpList\Core\Domain\Model\Interfaces\EmbeddableInterface;

#[ORM\Embeddable]
class MessageFormat implements EmbeddableInterface
{
    #[ORM\Column(name: 'htmlformatted', type: 'boolean', options: ['default' => false])]
    private bool $htmlFormatted = false;

    #[ORM\Column(name: 'sendformat', type: 'string', length: 20, nullable: true)]
    private ?string $sendFormat = null;

    #[ORM\Column(name: 'astext', type: 'boolean', options: ['default' => false])]
    private bool $asText = false;

    #[ORM\Column(name: 'ashtml', type: 'boolean', options: ['default' => false])]
    private bool $asHtml = false;

    #[ORM\Column(name: 'aspdf', type: 'boolean', options: ['default' => false])]
    private bool $asPdf = false;

    #[ORM\Column(name: 'astextandhtml', type: 'boolean', options: ['default' => false])]
    private bool $asTextAndHtml = false;

    #[ORM\Column(name: 'astextandpdf', type: 'boolean', options: ['default' => false])]
    private bool $asTextAndPdf = false;

    public const FORMAT_TEXT = 'text';
    public const FORMAT_HTML = 'html';
    public const FORMAT_PDF = 'pdf';

    public function __construct(
        bool $htmlFormatted,
        ?string $sendFormat,
        array $formatOptions = []
    ) {
        $this->htmlFormatted = $htmlFormatted;
        $this->sendFormat = $sendFormat;

        $this->setFormatOptions($formatOptions);
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

    public function getFormatOptions(): array
    {
        return array_values(array_filter([
            $this->asText ? self::FORMAT_TEXT : null,
            $this->asHtml ? self::FORMAT_HTML : null,
            $this->asPdf ? self::FORMAT_PDF : null,
        ]));
    }

    public function setFormatOptions(array $formatOptions): self
    {
        foreach ($formatOptions as $option) {
            match ($option) {
                self::FORMAT_TEXT => $this->asText = true,
                self::FORMAT_HTML => $this->asHtml = true,
                self::FORMAT_PDF => $this->asPdf = true,
                default => throw new InvalidArgumentException('Invalid format option: ' . $option)
            };
        }

        $this->asTextAndHtml = $this->asText && $this->asHtml;
        $this->asTextAndPdf = $this->asText && $this->asPdf;

        return $this;
    }
}
