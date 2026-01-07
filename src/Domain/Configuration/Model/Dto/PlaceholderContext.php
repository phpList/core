<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Model\Dto;

use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Subscription\Model\Subscriber;

final class PlaceholderContext
{
    public function __construct(
        public readonly Subscriber $subscriber,
        public readonly OutputFormat $format,
        public readonly string $locale = 'en',
        public readonly array $options = [],
    ) {}

    public function isHtml(): bool
    {
        return $this->format === OutputFormat::Html;
    }

    public function isText(): bool
    {
        return $this->format === OutputFormat::Text;
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function getUser(): Subscriber
    {
        return $this->subscriber;
    }
}
