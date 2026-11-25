<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Model\Dto;

class TldRefreshResult
{
    public const SOURCE_REMOTE = 'remote';
    public const SOURCE_BUNDLED = 'bundled';
    public const SOURCE_KEPT = 'kept';
    public const SOURCE_SKIPPED = 'skipped';

    /**
     * @param string $source one of the SOURCE_* constants: where the stored list came from this run
     */
    public function __construct(
        public readonly int $count,
        public readonly string $source,
    ) {
    }

    /** True when the download failed, whether or not an older or bundled list is still in use. */
    public function remoteFailed(): bool
    {
        return $this->source === self::SOURCE_BUNDLED || $this->source === self::SOURCE_KEPT;
    }
}
