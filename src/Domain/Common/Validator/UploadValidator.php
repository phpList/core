<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\Validator;

use PhpList\Core\Domain\Common\Exception\InvalidUploadException;
use PhpList\Core\Domain\Common\Exception\MissingUploadException;
use PhpList\Core\Domain\Common\Exception\UnsupportedExtensionException;
use PhpList\Core\Domain\Common\Exception\UnsupportedMimeTypeException;
use PhpList\Core\Domain\Common\Exception\UploadTooLargeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\Translation\TranslatorInterface;

class UploadValidator
{
    /**
     * @param string[] $allowedMimeTypes
     * @param string[] $allowedExtensions
     */
    public function __construct(
        #[Autowire('%uploads.allowed_mime_types%')]
        private readonly array $allowedMimeTypes,
        #[Autowire('%uploads.allowed_extensions%')]
        private readonly array $allowedExtensions,
        #[Autowire('%uploads.max_size%')]
        private readonly string $maxSize,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function validate(?UploadedFile $file): UploadedFile
    {
        $file = $this->validateUploadedFile($file);

        $this->validateMimeType($file);
        $this->validateExtension($file);
        $this->validateSize($file);

        return $file;
    }

    private function validateUploadedFile(?UploadedFile $file): UploadedFile
    {
        if ($file === null) {
            throw new MissingUploadException($this->translator->trans('No file uploaded.'));
        }

        if (!$file->isValid()) {
            throw new InvalidUploadException($this->translator->trans('Invalid uploaded file.'));
        }

        return $file;
    }

    private function validateMimeType(UploadedFile $file): void
    {
        $mimeType = strtolower((string) $file->getMimeType());

        if ($mimeType === '') {
            throw new InvalidUploadException(
                $this->translator->trans('Could not determine uploaded file MIME type.')
            );
        }

        if ($this->allowedMimeTypes !== [] && !in_array($mimeType, $this->allowedMimeTypes, true)) {
            throw new UnsupportedMimeTypeException(
                $this->translator->trans('Unsupported MIME type "%type%".', ['%type%' => $mimeType])
            );
        }
    }

    private function validateExtension(UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === '') {
            throw new InvalidUploadException($this->translator->trans('Uploaded file must have an extension.'));
        }

        if ($this->allowedExtensions !== [] && !in_array($extension, $this->allowedExtensions, true)) {
            throw new UnsupportedExtensionException(
                $this->translator->trans('Unsupported file extension "%extension%".', ['%extension%' => $extension])
            );
        }
    }

    private function validateSize(UploadedFile $file): void
    {
        $maxSize = $this->parseSizeLimit($this->maxSize);

        if ($file->getSize() > $maxSize) {
            throw new UploadTooLargeException(
                $this->translator->trans('Upload exceeds maximum size of %size% bytes.', ['%size%' => $maxSize])
            );
        }
    }

    private function parseSizeLimit(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return PHP_INT_MAX;
        }

        if (preg_match('/^(\d+)([kKmMgG]?[bB]?)?$/', $value, $matches) !== 1) {
            throw new InvalidUploadException(sprintf('Invalid upload size limit "%s".', $value));
        }

        $size = (int) $matches[1];
        $unit = strtolower((string) ($matches[2] ?? ''));

        return match (true) {
            $unit === '' || $unit === 'b' => $size,
            str_starts_with($unit, 'k') => $size * 1024,
            str_starts_with($unit, 'm') => $size * 1024 * 1024,
            str_starts_with($unit, 'g') => $size * 1024 * 1024 * 1024,
            default => throw new InvalidUploadException(sprintf('Invalid upload size limit "%s".', $value)),
        };
    }
}
