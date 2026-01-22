<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common;

use Throwable;

class FileHelper
{
    public function isValidFile(string $path): bool
    {
        return is_file($path) && filesize($path);
    }

    public function readFileContents(string $path): ?string
    {
        $filePointer = fopen($path, 'rb');
        if ($filePointer === false) {
            return null;
        }

        try {
            $contents = stream_get_contents($filePointer);
            if ($contents === false) {
                return null;
            }
            return $contents;
        } catch (Throwable) {
            return null;
        } finally {
            fclose($filePointer);
        }
    }

    public function writeFileToDirectory(string $directory, string $originalFilename, string $contents): ?string
    {
        $pathInfo = pathinfo($originalFilename);
        $name = $pathInfo['filename'] === '' ? 'file' : $pathInfo['filename'];
        $ext = $pathInfo['extension'] ?? '';

        $newFile = tempnam($directory, $name);
        if ($newFile === false) {
            return null;
        }

        if ($ext !== '') {
            $newFile .= '.' . $ext;
        }
        $relativeName = basename($newFile);

        $fullPath = $directory . '/' . $relativeName;

        $fileHandle = fopen($fullPath, 'w');

        fwrite($fileHandle, $contents);
        fclose($fileHandle);

        return $fullPath;
    }
}
