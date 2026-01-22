<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Common;

use PhpList\Core\Domain\Common\FileHelper;
use PHPUnit\Framework\TestCase;

class FileHelperTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/phplist-filehelper-' . bin2hex(random_bytes(6));
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            foreach (glob($this->tmpDir . '/*') ?: [] as $path) {
                if (is_file($path) || is_link($path)) {
                    unlink($path);
                }
            }
            rmdir($this->tmpDir);
        }
    }

    public function testIsValidFile(): void
    {
        $helper = new FileHelper();

        $nonExisting = $this->tmpDir . '/missing.txt';
        $this->assertFalse($helper->isValidFile($nonExisting), 'Non-existing path must be invalid');

        $empty = $this->tmpDir . '/empty.txt';
        touch($empty);
        $this->assertFalse($helper->isValidFile($empty), 'Empty file must be invalid');

        $nonEmpty = $this->tmpDir . '/data.txt';
        file_put_contents($nonEmpty, 'abc');
        $this->assertTrue($helper->isValidFile($nonEmpty), 'Non-empty file must be valid');

        $this->assertFalse($helper->isValidFile($this->tmpDir), 'Directory must be invalid');
    }

    public function testReadFileContents(): void
    {
        $helper = new FileHelper();

        $file = $this->tmpDir . '/readme.txt';
        $content = 'Hello, world!';
        file_put_contents($file, $content);

        $this->assertSame($content, $helper->readFileContents($file));

        // Attempting to read a directory should return null
        $this->assertNull($helper->readFileContents($this->tmpDir));
    }

    public function testWriteFileToDirectoryCreatesFileWithExtensionAndContents(): void
    {
        $helper = new FileHelper();

        $writtenPath = $helper->writeFileToDirectory($this->tmpDir, 'report.txt', 'payload');

        $this->assertNotNull($writtenPath);
        $this->assertStringStartsWith($this->tmpDir . '/', $writtenPath);
        $this->assertTrue(is_file($writtenPath));
        $this->assertStringEndsWith('.txt', basename($writtenPath));
        $this->assertSame('payload', file_get_contents($writtenPath));
    }

    public function testWriteFileToDirectoryUsesDefaultNameWhenMissing(): void
    {
        $helper = new FileHelper();

        $writtenPath = $helper->writeFileToDirectory($this->tmpDir, '', 'x');

        $this->assertNotNull($writtenPath);
        $this->assertTrue(is_file($writtenPath));
        $this->assertSame('x', file_get_contents($writtenPath));
        $this->assertStringStartsWith('file', basename($writtenPath));
    }
}
