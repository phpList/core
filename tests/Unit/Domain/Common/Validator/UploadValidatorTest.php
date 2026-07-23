<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Common\Validator;

use PhpList\Core\Domain\Common\Exception\InvalidUploadException;
use PhpList\Core\Domain\Common\Exception\MissingUploadException;
use PhpList\Core\Domain\Common\Exception\UnsupportedExtensionException;
use PhpList\Core\Domain\Common\Exception\UnsupportedMimeTypeException;
use PhpList\Core\Domain\Common\Exception\UploadTooLargeException;
use PhpList\Core\Domain\Common\Validator\UploadValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploadValidatorTest extends TestCase
{
    public function testValidateAcceptsConfiguredImageFormats(): void
    {
        $validator = new UploadValidator(
            ['image/jpeg', 'image/png', 'image/webp'],
            ['jpg', 'jpeg', 'png', 'webp'],
            '10M'
        );

        foreach ([
                ['photo.jpg', 'image/jpeg'],
                ['photo.png', 'image/png'],
                ['photo.webp', 'image/webp'],
            ] as [$originalName, $mimeType]
        ) {
            $file = $this->createFileMock($originalName, $mimeType, 1234, true);
            self::assertSame($file, $validator->validate($file));
        }
    }

    public function testValidateRejectsMissingFile(): void
    {
        $validator = new UploadValidator(['image/png'], ['png'], '10M');

        $this->expectException(MissingUploadException::class);
        $validator->validate(null);
    }

    public function testValidateRejectsInvalidUpload(): void
    {
        $validator = new UploadValidator(['image/png'], ['png'], '10M');
        $file = $this->createFileMock('photo.png', 'image/png', 1234, false);

        $this->expectException(InvalidUploadException::class);
        $validator->validate($file);
    }

    public function testValidateRejectsUnsupportedMimeType(): void
    {
        $validator = new UploadValidator(['image/png'], ['png'], '10M');
        $file = $this->createFileMock('photo.gif', 'image/gif', 1234, true);

        $this->expectException(UnsupportedMimeTypeException::class);
        $validator->validate($file);
    }

    public function testValidateRejectsUnsupportedExtension(): void
    {
        $validator = new UploadValidator(['image/png'], ['png'], '10M');
        $file = $this->createFileMock('photo.gif', 'image/png', 1234, true);

        $this->expectException(UnsupportedExtensionException::class);
        $validator->validate($file);
    }

    public function testValidateRejectsOversizedUpload(): void
    {
        $validator = new UploadValidator(['image/png'], ['png'], '1K');
        $file = $this->createFileMock('photo.png', 'image/png', 2048, true);

        $this->expectException(UploadTooLargeException::class);
        $validator->validate($file);
    }

    public function testValidateRejectsMissingExtension(): void
    {
        $validator = new UploadValidator(['image/png'], ['png'], '10M');
        $file = $this->createFileMock('photo', 'image/png', 1234, true);

        $this->expectException(InvalidUploadException::class);
        $validator->validate($file);
    }

    private function createFileMock(
        string $originalName,
        string $mimeType,
        int $size,
        bool $isValid
    ): UploadedFile {
        $file = $this->createMock(UploadedFile::class);
        $file->method('getMimeType')->willReturn($mimeType);
        $file->method('getClientOriginalExtension')->willReturn(pathinfo($originalName, PATHINFO_EXTENSION));
        $file->method('getSize')->willReturn($size);
        $file->method('isValid')->willReturn($isValid);

        return $file;
    }
}
