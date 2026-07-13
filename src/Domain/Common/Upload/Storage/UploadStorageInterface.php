<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\Upload\Storage;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface UploadStorageInterface
{
    public function store(UploadedFile $file, string $filename): StoredFile;
}
