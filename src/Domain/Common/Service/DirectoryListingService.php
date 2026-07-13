<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\Service;

use RuntimeException;

class DirectoryListingService
{
    /**
     * @return array<int, array{
     *     name: string,
     *     path: string,
     *     size: int,
     *     type: string,
     *     modified: int
     * }>
     */
    public function list(string $directory, string $realPath): array
    {
        $items = $this->readDirectory($realPath, $directory);

        $files = [];

        foreach ($items as $item) {
            $entry = $this->createEntry($directory, $realPath, $item);

            if ($entry !== null) {
                $files[] = $entry;
            }
        }

        $this->sortFiles($files);

        return $files;
    }

    private function readDirectory(string $realPath, string $directory): array
    {
        $items = scandir($realPath);

        if ($items === false) {
            throw new RuntimeException(
                sprintf('Unable to read directory "%s".', $directory)
            );
        }

        return $items;
    }

    private function sortFiles(array &$files): void
    {
        usort(
            $files,
            static function (array $file1, array $file2): int {
                if ($file1['type'] !== $file2['type']) {
                    return $file1['type'] === 'directory' ? -1 : 1;
                }

                return strcmp($file1['name'], $file2['name']);
            }
        );
    }

    /**
     * @return array{
     *     name:string,
     *     path:string,
     *     size:int,
     *     type:string,
     *     modified:int
     * }|null
     */
    private function createEntry(string $directory, string $realPath, string $item,): ?array
    {
        if ($item === '.' || $item === '..') {
            return null;
        }

        $fullPath = $realPath . DIRECTORY_SEPARATOR . $item;

        if (!is_file($fullPath) && !is_dir($fullPath)) {
            return null;
        }

        $isDirectory = is_dir($fullPath);

        return [
            'name' => $item,
            'path' => '/' . trim($directory, '/') . '/' . $item,
            'size' => $isDirectory ? 0 : (filesize($fullPath) ?: 0),
            'type' => $isDirectory ? 'directory' : 'file',
            'modified' => filemtime($fullPath) ?: 0,
        ];
    }
}
