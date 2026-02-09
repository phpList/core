<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model\Dto;

use Psr\Http\Message\StreamInterface;

final class DownloadableAttachment
{
    public function __construct(
        public readonly string $filename,
        public readonly string $mimeType,
        public readonly ?int $size,
        public readonly StreamInterface $content,
    ) {
    }
}
