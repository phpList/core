<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service;

use PhpList\Core\Domain\Common\ExternalImageService;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Manager\ConfigManager;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Model\TemplateImage;
use PhpList\Core\Domain\Messaging\Repository\TemplateImageRepository;
use PhpList\Core\Domain\Messaging\Service\TemplateImageEmbedder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TemplateImageEmbedderTest extends TestCase
{
    private ConfigProvider&MockObject $configProvider;
    private ConfigManager&MockObject $configManager;
    private ExternalImageService&MockObject $externalImageService;
    private TemplateImageRepository&MockObject $templateImageRepository;

    private string $documentRoot;

    protected function setUp(): void
    {
        $this->configProvider = $this->createMock(ConfigProvider::class);
        $this->configManager = $this->createMock(ConfigManager::class);
        $this->externalImageService = $this->createMock(ExternalImageService::class);
        $this->templateImageRepository = $this->createMock(TemplateImageRepository::class);

        // Create a temporary document root for filesystem-related tests
        $this->documentRoot = sys_get_temp_dir() . '/tpl_img_embedder_' . bin2hex(random_bytes(6));
        mkdir($this->documentRoot, 0777, true);

        // Reasonable defaults for options used in code
        $this->configProvider->method('getValue')->willReturnMap([
            [ConfigOption::SystemMessageTemplate, '0'],
            [ConfigOption::Website, 'https://example.com'],
            [ConfigOption::UploadImageRoot, $this->documentRoot . '/upload/'],
            [ConfigOption::PageRoot, '/'],
        ]);
    }

    protected function tearDown(): void
    {
        // best-effort cleanup
        if (is_dir($this->documentRoot)) {
            $this->recursiveRemove($this->documentRoot);
        }
    }

    private function recursiveRemove(string $path): void
    {
        if (!is_dir($path)) {
            unlink($path);
            return;
        }
        foreach (scandir($path) ?: [] as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $full = $path . DIRECTORY_SEPARATOR . $file;
            if (is_dir($full)) {
                $this->recursiveRemove($full);
            } else {
                unlink($full);
            }
        }
        rmdir($path);
    }

    private function createEmbedder(
        bool $embedExternal = false,
        bool $embedUploaded = false,
        ?string $uploadImagesDir = null,
        string $editorImagesDir = 'images'
    ): TemplateImageEmbedder {
        return new TemplateImageEmbedder(
            configProvider: $this->configProvider,
            configManager: $this->configManager,
            externalImageService: $this->externalImageService,
            templateImageRepository: $this->templateImageRepository,
            documentRoot: $this->documentRoot,
            editorImagesDir: $editorImagesDir,
            embedExternalImages: $embedExternal,
            embedUploadedImages: $embedUploaded,
            uploadImagesDir: $uploadImagesDir,
        );
    }

    public function testExternalImagesEmbeddedAndSameHostLeftAlone(): void
    {
        $this->configProvider->method('getValue')->willReturnMap([
            [ConfigOption::SystemMessageTemplate, '0'],
            [ConfigOption::Website, 'https://example.com'],
            [ConfigOption::UploadImageRoot, $this->documentRoot . '/upload/'],
            [ConfigOption::PageRoot, '/'],
        ]);

        $html = '<p><img src="https://cdn.other.org/pic.jpg"> and '
              . '<img src="https://example.com/local.jpg"></p>';

        $this->externalImageService->expects($this->exactly(2))
            ->method('cache')
            ->withConsecutive(
                ['https://cdn.other.org/pic.jpg', 111],
                ['https://example.com/local.jpg', 111]
            )
            ->willReturnOnConsecutiveCalls(true, false);

        $jpegBase64 = base64_encode('JPEGDATA');
        $this->externalImageService->expects($this->once())
            ->method('getFromCache')
            ->with('https://cdn.other.org/pic.jpg', 111)
            ->willReturn($jpegBase64);

        $embedder = $this->createEmbedder(embedExternal: true);
        $out = $embedder($html, 111);

        $this->assertStringContainsString('cid:', $out);
        $this->assertStringContainsString('https://example.com/local.jpg', $out, 'Same-host URL should remain');
        $this->assertCount(1, $embedder->attachment);
        $att = $embedder->attachment[0];
        $this->assertSame('base64', $att[3]);
        $this->assertSame('image/jpeg', $att[4]);
    }

    public function testTemplateImagesAreEmbeddedIncludingPoweredBySpecialCase(): void
    {
        // Template id used
        $this->configProvider->method('getValue')->willReturnMap([
            [ConfigOption::SystemMessageTemplate, '42'],
            [ConfigOption::Website, 'https://example.com'],
            [ConfigOption::UploadImageRoot, $this->documentRoot . '/upload/'],
            [ConfigOption::PageRoot, '/'],
        ]);

        $html = '<div><img src="/assets/logo.jpg"><img src="powerphplist.png"></div>';

        // For normal image, repository called with templateId 42
        $tplImg1 = $this->createMock(TemplateImage::class);
        $tplImg1->method('getData')->willReturn(base64_encode('IMG1'));

        // For powerphplist.png, templateId should be 0 per implementation
        $tplImg2 = $this->createMock(TemplateImage::class);
        $tplImg2->method('getData')->willReturn(base64_encode('IMG2'));

        $this->templateImageRepository->method('findByTemplateIdAndFilename')
            ->willReturnCallback(function (int $tplId, string $filename) use ($tplImg1, $tplImg2) {
                if ($filename === '/assets/logo.jpg') {
                    // In current implementation, first pass checks templateId as provided
                    return $tplImg1;
                }
                if ($filename === 'powerphplist.png') {
                    return $tplImg2;
                }
                return null;
            });

        $embedder = $this->createEmbedder();
        $out = $embedder($html, 7);

        // Both images should be replaced with cid references
        $this->assertSame(2, substr_count($out, 'cid:'));
        $this->assertStringNotContainsString('/assets/logo.jpg', $out);
        $this->assertStringNotContainsString('powerphplist.png"', $out, 'basename is replaced by cid');
        $this->assertCount(2, $embedder->attachment);
    }

    public function testFilesystemUploadedImagesAreEmbeddedAndConfigIsUpdated(): void
    {
        // Prepare upload dir structure and file
        $uploadDir = $this->documentRoot . '/uploads';
        mkdir($uploadDir . '/image', 0777, true);
        $filePath = $uploadDir . '/image/pic.png';
        file_put_contents($filePath, 'PNGDATA');

        $this->configProvider->method('getValue')->willReturnMap([
            [ConfigOption::SystemMessageTemplate, '0'],
            [ConfigOption::Website, 'https://example.com'],
            [ConfigOption::UploadImageRoot, $this->documentRoot . '/upload/'],
            [ConfigOption::PageRoot, '/'],
        ]);

        // Expect configManager->create called when a path with non-null config is used
        $this->configManager->expects($this->atLeastOnce())
            ->method('create');

        $html = '<p><img src="' . $filePath . '"></p>';

        $embedder = $this->createEmbedder(embedUploaded: true, uploadImagesDir: 'uploads');
        $out = $embedder($html, 22);

        $this->assertStringContainsString('cid:', $out);
        $this->assertCount(1, $embedder->attachment);
        $att = $embedder->attachment[0];
        $this->assertSame('image/png', $att[4]);
    }

    public function testNoOpWhenFlagsOffAndNoTemplateMatch(): void
    {
        $this->configProvider->method('getValue')->willReturnMap([
            [ConfigOption::SystemMessageTemplate, '0'],
            [ConfigOption::Website, 'https://example.com'],
            [ConfigOption::UploadImageRoot, $this->documentRoot . '/upload/'],
            [ConfigOption::PageRoot, '/'],
        ]);

        // Neither external nor uploaded embedding enabled; repository returns null
        $this->templateImageRepository->method('findByTemplateIdAndFilename')->willReturn(null);

        $html = '<img src="/not/a/template/image.jpg">';
        $embedder = $this->createEmbedder();
        $out = $embedder($html, 1);

        $this->assertSame($html, $out);
        $this->assertSame([], $embedder->attachment);
    }

    public function testUnknownExtensionIsIgnored(): void
    {
        $this->configProvider->method('getValue')->willReturnMap([
            [ConfigOption::SystemMessageTemplate, 0],
            [ConfigOption::Website, 'https://example.com'],
            [ConfigOption::UploadImageRoot, $this->documentRoot . '/upload/'],
            [ConfigOption::PageRoot, '/'],
        ]);

        $html = '<img src="/assets/vector.svg">';
        $embedder = $this->createEmbedder(embedExternal: true, embedUploaded: true);
        $out = $embedder($html, 5);

        // .svg is not in allowed extensions → untouched, no attachments
        $this->assertSame($html, $out);
        $this->assertSame([], $embedder->attachment);
    }
}
