<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Analytics\Model\Dto;

/** Identifies one tracked link of one campaign for one subscriber, as carried in a click-tracking URL. */
final class LinkTrackToken
{
    public const TYPE_HTML = 'H';
    public const TYPE_TEXT = 'T';

    public function __construct(
        public readonly string $type,
        public readonly string $forwardUuid,
        public readonly string $messageUuid,
        public readonly string $subscriberUuid,
    ) {
    }

    public function isHtml(): bool
    {
        return $this->type === self::TYPE_HTML;
    }

    public static function isValidType(string $type): bool
    {
        return in_array($type, [self::TYPE_HTML, self::TYPE_TEXT], true);
    }
}
