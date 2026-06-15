<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Model\Dto;

class CreateConfigDto
{
    public function __construct(
        public readonly string $key,
        public readonly string $value,
        public readonly bool $editable,
        public readonly ?string $type = null,
    ) {
    }
}
