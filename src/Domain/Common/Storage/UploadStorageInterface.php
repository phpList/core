<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\Storage;

use PhpList\Core\Domain\Common\Model\StoredFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;

interface UploadStorageInterface
{
    public function store(UploadedFile $file, string $filename): StoredFile;
}
