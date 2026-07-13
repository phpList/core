<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\Service;

use PhpList\Core\Domain\Common\Exception\InvalidUploadException;
use PhpList\Core\Domain\Common\Exception\StorageException;
use PhpList\Core\Domain\Common\Model\UploadResult;
use PhpList\Core\Domain\Common\Storage\UploadStorageInterface;
use PhpList\Core\Domain\Common\Validator\UploadValidator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;

class UploadService
{
    public function __construct(
        private readonly UploadValidator $validator,
        private readonly UploadStorageInterface $storage,
        private readonly RequestStack $requestStack,
        #[Autowire('%phplist.upload_images_dir%')]
        private readonly string $uploadDirectory,
    ) {
    }

    public function upload(?UploadedFile $file): UploadResult
    {
        $validatedFile = $this->validator->validate($file);
        $extension = strtolower(($validatedFile->guessExtension() ?: $validatedFile->getClientOriginalExtension()));
        if ($extension === '') {
            throw new InvalidUploadException('Could not determine file extension.');
        }

        $filename = $this->generateFilename($extension);
        $storedFile = $this->storage->store($validatedFile, $filename);

        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            throw new StorageException('No current request available for public URL generation.');
        }

        $publicPath = trim($this->uploadDirectory, '/\\');
        $url = rtrim($request->getSchemeAndHttpHost(), '/') . '/' . $publicPath . '/' . $storedFile->getFilename();

        return new UploadResult(
            filename: $storedFile->getFilename(),
            url: $url,
            mimeType: $storedFile->getMimeType(),
            size: $storedFile->getSize(),
            extension: $storedFile->getExtension() !== '' ? $storedFile->getExtension() : $extension,
        );
    }

    private function generateFilename(string $extension): string
    {
        return bin2hex(random_bytes(16)) . '.' . ltrim($extension, '.');
    }
}
