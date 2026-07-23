<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\Model;

class StoredFile
{
    public function __construct(
        private readonly string $filename,
        private readonly string $path,
        private readonly string $mimeType,
        private readonly int $size,
        private readonly string $extension,
    ) {
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getPath(): string
    {
        return $this->path;
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
