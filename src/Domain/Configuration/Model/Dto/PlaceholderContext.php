<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Model\Dto;

use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Subscription\Model\Subscriber;

final class PlaceholderContext
{
    public function __construct(
        public readonly Subscriber $user,
        public readonly OutputFormat $format,
        public readonly string $locale = 'en',
        private readonly ?string $forwardedBy = null
    ) {}

    public function isHtml(): bool
    {
        return $this->format === OutputFormat::Html;
    }

    public function isText(): bool
    {
        return $this->format === OutputFormat::Text;
    }

    public function forwardedBy(): ?string
    {
        return $this->forwardedBy;
    }

    public function getUser(): Subscriber
    {
        return $this->user;
    }
}
