<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\Model\Dto;

class DirectoryEntryDto
{
    public function __construct(
        public readonly string $name,
        public readonly string $path,
        public readonly int $size,
        public readonly string $type,
        public readonly int $modified,
    ) {
    }
}
