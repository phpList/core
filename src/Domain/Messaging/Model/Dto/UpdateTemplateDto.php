<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model\Dto;

class UpdateTemplateDto
{
    /**
     * @SuppressWarnings("BooleanArgumentFlag")
     */
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $content = null,
        public readonly ?string $text = null,
        public readonly ?string $fileContent = null,
        public readonly bool $shouldCheckLinks = false,
        public readonly bool $shouldCheckImages = false,
        public readonly bool $shouldCheckExternalImages = false,
    ) {
    }
}
