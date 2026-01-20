<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common;

class FileHelper
{
    public function isValidFile(string $path): bool
    {
        return is_file($path) && filesize($path);
    }

    public function readFileContents(string $path): ?string
    {
        $filePointer = fopen($path, 'r');
        if ($filePointer === false) {
            return null;
        }

        $contents = fread($filePointer, filesize($path));
        fclose($filePointer);

        return $contents === false ? null : $contents;
    }
}
