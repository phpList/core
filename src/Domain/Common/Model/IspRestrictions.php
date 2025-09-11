<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\Model;

final class IspRestrictions
{
    public function __construct(
        public readonly ?int $maxBatch,
        public readonly ?int $minBatchPeriod,
        public readonly ?string $lockFile,
        public readonly array $raw = [],
    )
    {
    }

    public function isEmpty(): bool
    {
        return $this->maxBatch === null && $this->minBatchPeriod === null && $this->lockFile === null;
    }
}
