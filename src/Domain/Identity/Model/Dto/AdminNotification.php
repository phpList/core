<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Model\Dto;

/**
 * An in-app notification for an administrator (the phplist3 Info/Warn/Error banners).
 */
final class AdminNotification
{
    public const LEVEL_INFO = 'info';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_ERROR = 'error';

    /**
     * @param array<string, scalar|null> $context machine readable details, e.g. counts
     */
    public function __construct(
        public readonly string $code,
        public readonly string $level,
        public readonly string $message,
        public readonly array $context = [],
    ) {
    }
}
