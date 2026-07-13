<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Common\Upload;

use PhpList\Core\Domain\Common\Upload\Exception\StorageException;
use PhpList\Core\Domain\Common\Upload\Storage\StoredFile;
use PhpList\Core\Domain\Common\Upload\Storage\UploadStorageInterface;
use PhpList\Core\Domain\Common\Upload\UploadResult;
use PhpList\Core\Domain\Common\Upload\UploadService;
use PhpList\Core\Domain\Common\Upload\UploadValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class UploadServiceTest extends TestCase
{
    public function testUploadReturnsResultAndGeneratesUniqueFilenames(): void
    {
        $validator = $this->createMock(UploadValidator::class);
        $storage = $this->createMock(UploadStorageInterface::class);
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://example.test/api/v2/editor-uploads'));
        $file = $this->createMock(UploadedFile::class);
        $file->method('guessExtension')->willReturn('png');
        $file->method('getClientOriginalExtension')->willReturn('png');

        $validator->expects(self::exactly(2))
            ->method('validate')
            ->with($file)
            ->willReturn($file);

        $filenames = [];
        $storage->expects(self::exactly(2))
            ->method('store')
            ->willReturnCallback(function (UploadedFile $uploadedFile, string $filename) use (&$filenames): StoredFile {
                $filenames[] = $filename;

                return new StoredFile($filename, '/tmp/' . $filename, 'image/png', 123, 'png');
            });

        $service = new UploadService($validator, $storage, $requestStack, 'uploadfiles');

        $firstResult = $service->upload($file);
        $secondResult = $service->upload($file);

        self::assertInstanceOf(UploadResult::class, $firstResult);
        self::assertSame('image/png', $firstResult->getMimeType());
        self::assertSame(123, $firstResult->getSize());
        self::assertSame('png', $firstResult->getExtension());
        self::assertStringStartsWith('https://example.test/uploadfiles/', $firstResult->getUrl());
        self::assertCount(2, array_unique($filenames));
        self::assertNotSame($firstResult->getFilename(), $secondResult->getFilename());
    }

    public function testUploadPropagatesStorageExceptions(): void
    {
        $validator = $this->createMock(UploadValidator::class);
        $storage = $this->createMock(UploadStorageInterface::class);
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://example.test/api/v2/editor-uploads'));
        $file = $this->createMock(UploadedFile::class);
        $file->method('guessExtension')->willReturn('png');
        $file->method('getClientOriginalExtension')->willReturn('png');

        $validator->method('validate')->willReturn($file);
        $storage->method('store')->willThrowException(new StorageException('storage down'));

        $service = new UploadService($validator, $storage, $requestStack, 'uploadfiles');

        $this->expectException(StorageException::class);
        $service->upload($file);
    }
}
