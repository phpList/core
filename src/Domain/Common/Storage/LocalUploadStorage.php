<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\Storage;

use PhpList\Core\Domain\Common\Exception\StorageException;
use PhpList\Core\Domain\Common\Model\StoredFile;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\Translation\TranslatorInterface;

class LocalUploadStorage implements UploadStorageInterface
{
    public function __construct(
        #[Autowire('%kernel.application_dir%')]
        private readonly string $projectDir,
        #[Autowire('%uploads.images_dir%')]
        private readonly string $uploadDirectory,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function store(UploadedFile $file, string $filename): StoredFile
    {
        $targetDirectory = $this->projectDir
            . DIRECTORY_SEPARATOR
            . 'public'
            . DIRECTORY_SEPARATOR
            . trim($this->uploadDirectory, '/\\');
        $mimeType = (string) ($file->getMimeType() ?? 'application/octet-stream');
        $size = (int) $file->getSize();
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
            throw new StorageException($this->translator->trans('Could not create upload directory.'));
        }

        try {
            $file->move($targetDirectory, $filename);
        } catch (FileException $exception) {
            throw new StorageException($this->translator->trans('Could not store uploaded file.'), 0, $exception);
        }

        $storedPath = $targetDirectory . DIRECTORY_SEPARATOR . $filename;

        return new StoredFile($filename, $storedPath, $mimeType, $size, $extension);
    }
}
