<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Common\Upload\Storage;

use PhpList\Core\Domain\Common\Upload\Exception\StorageException;
use PhpList\Core\Domain\Common\Upload\Storage\LocalUploadStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class LocalUploadStorageTest extends TestCase
{
    private string $projectDir;
    private string $uploadDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->projectDir = sys_get_temp_dir() . '/phplist-upload-storage-' . bin2hex(random_bytes(4));
        $this->uploadDirectory = 'uploadfiles';
    }

    protected function tearDown(): void
    {
        $targetDirectory = $this->projectDir . '/public/' . $this->uploadDirectory;
        if (is_dir($targetDirectory)) {
            foreach (glob($targetDirectory . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($targetDirectory);
        }

        if (is_dir($this->projectDir . '/public')) {
            rmdir($this->projectDir . '/public');
        }

        if (is_dir($this->projectDir)) {
            rmdir($this->projectDir);
        }

        parent::tearDown();
    }

    public function testStoreCreatesDirectoryAndMovesFile(): void
    {
        $storage = new LocalUploadStorage($this->projectDir, $this->uploadDirectory);
        $tempFile = tempnam(sys_get_temp_dir(), 'upload-storage-');
        file_put_contents($tempFile, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jqN8AAAAASUVORK5CYII='
        ));

        $file = new UploadedFile($tempFile, 'photo.png', 'image/png', null, true);
        $storedFile = $storage->store($file, 'stored.png');

        self::assertSame('stored.png', $storedFile->getFilename());
        self::assertSame(
            $this->projectDir . '/public/' . $this->uploadDirectory . '/stored.png',
            $storedFile->getPath()
        );
        self::assertSame('image/png', $storedFile->getMimeType());
        self::assertGreaterThan(0, $storedFile->getSize());
        self::assertSame('png', $storedFile->getExtension());
        self::assertFileExists($storedFile->getPath());
    }

    public function testStoreWrapsFileExceptions(): void
    {
        $storage = new LocalUploadStorage($this->projectDir, $this->uploadDirectory);
        $file = $this->createMock(UploadedFile::class);
        $file->method('move')->willThrowException(new FileException('boom'));
        $file->method('getMimeType')->willReturn('image/png');
        $file->method('getSize')->willReturn(7);

        $this->expectException(StorageException::class);
        $storage->store($file, 'stored.png');
    }
}
