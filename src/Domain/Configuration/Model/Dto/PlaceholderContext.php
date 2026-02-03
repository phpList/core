<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Model\Dto;

use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Subscription\Model\Subscriber;

class PlaceholderContext
{
    public function __construct(
        public readonly Subscriber $user,
        public readonly OutputFormat $format,
        public readonly ?MessagePrecacheDto $messagePrecacheDto = null,
        public readonly string $locale = 'en',
        private readonly ?Subscriber $forwardedBy = null,
        private readonly ?int $messageId = null,
    ) {
    }

    public function isHtml(): bool
    {
        return $this->format === OutputFormat::Html;
    }

    public function isText(): bool
    {
        return $this->format === OutputFormat::Text;
    }

    public function forwardedBy(): ?Subscriber
    {
        return $this->forwardedBy;
    }

    public function messageId(): ?int
    {
        return $this->messageId;
    }

    public function getUser(): Subscriber
    {
        return $this->user;
    }
}
