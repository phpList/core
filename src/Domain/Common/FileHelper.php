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
        // tempnam() silently falls back to the system temp dir when the directory is unusable
        if (!is_dir($directory) || !is_writable($directory)) {
            return null;
        }

        $pathInfo = pathinfo($originalFilename);
        $name = $pathInfo['filename'] === '' ? 'file' : $pathInfo['filename'];
        $ext = $pathInfo['extension'] ?? '';

        $tempFile = tempnam($directory, $name);
        if ($tempFile === false) {
            return null;
        }

        $fullPath = $directory . '/' . basename($tempFile);
        if ($ext !== '') {
            $fullPath .= '.' . $ext;
            if (!rename($tempFile, $fullPath)) {
                unlink($tempFile);

                return null;
            }
        }

        if (file_put_contents($fullPath, $contents) !== strlen($contents)) {
            unlink($fullPath);

            return null;
        }

        return $fullPath;
    }
}
