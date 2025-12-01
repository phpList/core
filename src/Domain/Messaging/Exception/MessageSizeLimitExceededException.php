<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Exception;

use RuntimeException;

class MessageSizeLimitExceededException extends RuntimeException
{
    public function __construct(
        private readonly int $actualSize,
        private readonly int $maxSize
    ) {
        parent::__construct(sprintf(
            'Message too large (%d bytes exceeds limit of %d bytes)',
            $actualSize,
            $maxSize
        ));
    }

    public function getActualSize(): int
    {
        return $this->actualSize;
    }

    public function getMaxSize(): int
    {
        return $this->maxSize;
    }
}
