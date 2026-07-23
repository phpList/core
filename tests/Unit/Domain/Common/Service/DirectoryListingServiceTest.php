<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Common\Service;

use PhpList\Core\Domain\Common\Model\Dto\DirectoryEntryDto;
use PhpList\Core\Domain\Common\Service\DirectoryListingService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DirectoryListingServiceTest extends TestCase
{
    private string $realPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->realPath = sys_get_temp_dir() . '/phplist-directory-listing-' . bin2hex(random_bytes(4));
        mkdir($this->realPath);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->realPath . '/*') ?: [] as $entry) {
            is_dir($entry) ? rmdir($entry) : unlink($entry);
        }
        rmdir($this->realPath);

        parent::tearDown();
    }

    public function testListReturnsFilesAndDirectories(): void
    {
        file_put_contents($this->realPath . '/b.txt', 'hello');
        mkdir($this->realPath . '/a-folder');

        $service = new DirectoryListingService();
        $entries = $service->list('uploads', $this->realPath);

        self::assertCount(2, $entries);
        self::assertContainsOnlyInstancesOf(DirectoryEntryDto::class, $entries);
    }

    public function testListSortsDirectoriesBeforeFilesThenAlphabetically(): void
    {
        file_put_contents($this->realPath . '/z.txt', 'z');
        file_put_contents($this->realPath . '/a.txt', 'a');
        mkdir($this->realPath . '/y-folder');
        mkdir($this->realPath . '/b-folder');

        $service = new DirectoryListingService();
        $entries = $service->list('uploads', $this->realPath);

        self::assertSame(
            ['b-folder', 'y-folder', 'a.txt', 'z.txt'],
            array_map(static fn (DirectoryEntryDto $entry): string => $entry->name, $entries)
        );
    }

    public function testListBuildsPathFromDirectoryAndFileName(): void
    {
        file_put_contents($this->realPath . '/file.txt', 'content');

        $service = new DirectoryListingService();
        $entries = $service->list('/uploads/', $this->realPath);

        self::assertSame('uploads/file.txt', $entries[0]->path);
    }

    public function testListSetsSizeAndTypeForFiles(): void
    {
        file_put_contents($this->realPath . '/file.txt', 'content');

        $service = new DirectoryListingService();
        $entries = $service->list('uploads', $this->realPath);

        self::assertSame('file', $entries[0]->type);
        self::assertSame(7, $entries[0]->size);
        self::assertGreaterThan(0, $entries[0]->modified);
    }

    public function testListSetsSizeZeroAndTypeForDirectories(): void
    {
        mkdir($this->realPath . '/folder');

        $service = new DirectoryListingService();
        $entries = $service->list('uploads', $this->realPath);

        self::assertSame('directory', $entries[0]->type);
        self::assertSame(0, $entries[0]->size);
    }

    public function testListExcludesDotAndDotDotEntries(): void
    {
        $service = new DirectoryListingService();
        $entries = $service->list('uploads', $this->realPath);

        self::assertSame([], $entries);
    }

    public function testListThrowsRuntimeExceptionWhenDirectoryCannotBeRead(): void
    {
        $missingPath = $this->realPath . '/does-not-exist';

        $service = new DirectoryListingService();

        $this->expectException(RuntimeException::class);
        // phpcs:ignore Generic.PHP.NoSilencedErrors
        @$service->list('uploads', $missingPath);
    }
}
