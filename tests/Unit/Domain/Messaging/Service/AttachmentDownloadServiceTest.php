<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service;

use PhpList\Core\Domain\Messaging\Exception\AttachmentFileNotFoundException;
use PhpList\Core\Domain\Messaging\Model\Attachment;
use PhpList\Core\Domain\Messaging\Service\AttachmentDownloadService;
use PHPUnit\Framework\TestCase;

final class AttachmentDownloadServiceTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phplist-att-dl-' . bin2hex(random_bytes(5));
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        // cleanup temp directory
        if (is_dir($this->tempDir)) {
            $files = scandir($this->tempDir) ?: [];
            foreach ($files as $f) {
                if ($f === '.' || $f === '..') {
                    continue;
                }
                unlink($this->tempDir . '/' . $f);
            }
            rmdir($this->tempDir);
        }
    }

    public function testThrowsWhenFilenameIsEmpty(): void
    {
        $service = new AttachmentDownloadService($this->tempDir);

        $attachment = $this->createMock(Attachment::class);
        $attachment->method('getFilename')->willReturn('');

        $this->expectException(AttachmentFileNotFoundException::class);
        $service->getDownloadable($attachment);
    }

    public function testThrowsWhenFileDoesNotExist(): void
    {
        $service = new AttachmentDownloadService($this->tempDir);

        $attachment = $this->createMock(Attachment::class);
        $attachment->method('getFilename')->willReturn('missing-file.pdf');

        $this->expectException(AttachmentFileNotFoundException::class);
        $service->getDownloadable($attachment);
    }

    public function testReturnsDownloadableWithExplicitMimeType(): void
    {
        $service = new AttachmentDownloadService($this->tempDir);

        $filename = 'doc.pdf';
        $content = '%PDF-1.4\n';
        file_put_contents($this->tempDir . '/' . $filename, $content);

        $attachment = $this->createMock(Attachment::class);
        $attachment->method('getFilename')->willReturn($filename);
        $attachment->method('getMimeType')->willReturn('application/pdf');

        $dl = $service->getDownloadable($attachment);

        $this->assertSame($filename, $dl->filename);
        $this->assertSame('application/pdf', $dl->mimeType);
        $this->assertSame(strlen($content), $dl->size);
        $this->assertSame($content, (string)$dl->content);
    }

    public function testGuessesMimeTypeAndProvidesStream(): void
    {
        $service = new AttachmentDownloadService($this->tempDir);

        $filename = 'note.txt';
        $content = "Hello, world!\n";
        file_put_contents($this->tempDir . '/' . $filename, $content);

        $attachment = $this->createMock(Attachment::class);
        $attachment->method('getFilename')->willReturn($filename);
        $attachment->method('getMimeType')->willReturn(null);

        $dl = $service->getDownloadable($attachment);

        $this->assertSame($filename, $dl->filename);
        // Symfony MimeTypes should detect text/plain for .txt
        $this->assertSame('text/plain', $dl->mimeType);
        $this->assertSame(strlen($content), $dl->size);
        $this->assertSame($content, (string)$dl->content);
    }
}
