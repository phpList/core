<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Messaging\Dto;

final class CreateTemplateDto
{
    public function __construct(
        public readonly string $title,
        public readonly string $content,
        public readonly ?string $text = null,
        public readonly ?string $fileContent = null,
        public readonly bool $checkLinks = false,
        public readonly bool $checkImages = false,
        public readonly bool $checkExternalImages = false,
    ) {}
}
