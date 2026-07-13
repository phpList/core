<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\Upload;

class UploadResult
{
    public function __construct(
        private readonly string $filename,
        private readonly string $url,
        private readonly string $mimeType,
        private readonly int $size,
        private readonly string $extension,
    ) {
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getExtension(): string
    {
        return $this->extension;
    }
}
