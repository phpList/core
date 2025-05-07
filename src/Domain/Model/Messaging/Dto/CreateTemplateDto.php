<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Messaging\Dto;

class CreateTemplateDto
{
    /**
     * @SuppressWarnings("BooleanArgumentFlag")
     */
    public function __construct(
        public readonly string $title,
        public readonly string $content,
        public readonly ?string $text = null,
        public readonly ?string $fileContent = null,
        public readonly bool $shouldCheckLinks = false,
        public readonly bool $shouldCheckImages = false,
        public readonly bool $shouldCheckExternalImages = false,
    ) {
    }
}
