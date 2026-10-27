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
        $realDirectory = $this->resolveTrustedDirectory($directory);
        if ($realDirectory === null) {
            return null;
        }

        $pathInfo = pathinfo($originalFilename);
        $name = $pathInfo['filename'] === '' ? 'file' : $pathInfo['filename'];
        $ext = $pathInfo['extension'] ?? '';

        // Exclusive create (O_EXCL) never follows a symlink or reuses an existing entry,
        // so the file is created and written in a single step without rename.
        $fullPath = $realDirectory . '/' . $name . bin2hex(random_bytes(6)) . ($ext !== '' ? '.' . $ext : '');
        $handle = fopen($fullPath, 'xb');
        if ($handle === false) {
            return null;
        }

        $written = fwrite($handle, $contents);
        fclose($handle);
        if ($written !== strlen($contents)) {
            unlink($fullPath);

            return null;
        }

        return $fullPath;
    }

    private function resolveTrustedDirectory(string $directory): ?string
    {
        if (!is_dir($directory) || !is_writable($directory)) {
            return null;
        }

        $realDirectory = realpath($directory);
        if ($realDirectory === false) {
            return null;
        }

        // Untrusted writers could swap entries in the directory between checks and writes
        $perms = fileperms($realDirectory);
        if ($perms === false || (($perms & 0022) !== 0 && ($perms & 01000) === 0)) {
            return null;
        }

        return $realDirectory;
    }
}
