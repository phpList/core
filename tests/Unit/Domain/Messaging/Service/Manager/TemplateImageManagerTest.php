<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Manager;

use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Model\Template;
use PhpList\Core\Domain\Messaging\Model\TemplateImage;
use PhpList\Core\Domain\Messaging\Repository\TemplateImageRepository;
use PhpList\Core\Domain\Messaging\Service\Manager\ImageProvider;
use PhpList\Core\Domain\Messaging\Service\Manager\TemplateImageManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TemplateImageManagerTest extends TestCase
{
    private const ONE_PIXEL_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQMAAAAl21bKAAAABGdBTUEAALGPC/'
    . 'xhBQAAAAZQTFRF////AAAAVcLTfgAAAAF0Uk5TAEDm2GYAAAABYktHRACIBR1IAAAACXBIWXMAAAsSAAALEgHS3X78'
    . 'AAAAB3RJTUUH0gQCEx05cqKA8gAAAApJREFUeJxjYAAAAAIAAUivpHEAAAAASUVORK5CYII=';

    private TemplateImageRepository&MockObject $templateImageRepository;
    private ConfigProvider&MockObject $configProvider;
    private TemplateImageManager $manager;

    protected function setUp(): void
    {
        $this->templateImageRepository = $this->createMock(TemplateImageRepository::class);
        $this->configProvider = $this->createMock(ConfigProvider::class);

        $this->manager = new TemplateImageManager(
            templateImageRepository: $this->templateImageRepository,
            configProvider: $this->configProvider,
            imageProvider: $this->createMock(ImageProvider::class),
        );
    }

    public function testCreateImagesFromImagePaths(): void
    {
        $template = $this->createMock(Template::class);

        $this->templateImageRepository->method('poweredByImageExists')->willReturn(false);
        $this->templateImageRepository->expects($this->exactly(2 + 1))
            ->method('persist')
            ->with($this->isInstanceOf(TemplateImage::class));

        $images = $this->manager->createImagesFromImagePaths(['image1.jpg', 'image2.png'], $template);

        $this->assertCount(2, $images);
        foreach ($images as $image) {
            $this->assertInstanceOf(TemplateImage::class, $image);
        }
    }

    public function testCreateImagesFromImagePathsStoresImageDataAndReplacesExistingImage(): void
    {
        $template = $this->createMock(Template::class);
        $template->method('getId')->willReturn(77);

        $existingImage = $this->createMock(TemplateImage::class);
        $capturedImage = null;

        $tmpFile = tempnam(sys_get_temp_dir(), 'img_');
        if ($tmpFile === false) {
            $this->fail('Failed to create temporary file');
        }

        file_put_contents($tmpFile, (string) base64_decode(self::ONE_PIXEL_PNG, true));

        $this->templateImageRepository->method('poweredByImageExists')->willReturn(true);
        $this->templateImageRepository->expects($this->once())
            ->method('findByTemplateIdAndFilename')
            ->with(77, $tmpFile)
            ->willReturn($existingImage);
        $this->templateImageRepository->expects($this->once())
            ->method('remove')
            ->with($existingImage);
        $this->templateImageRepository->expects($this->once())
            ->method('persist')
            ->with($this->callback(static function (TemplateImage $image) use (&$capturedImage): bool {
                $capturedImage = $image;
                return true;
            }));

        try {
            $this->manager->createImagesFromImagePaths([$tmpFile], $template);
        } finally {
            unlink($tmpFile);
        }

        $this->assertInstanceOf(TemplateImage::class, $capturedImage);
        $this->assertSame(1, $capturedImage->getWidth());
        $this->assertSame(1, $capturedImage->getHeight());
        $this->assertSame('image/png', $capturedImage->getMimeType());
        $this->assertSame(self::ONE_PIXEL_PNG, $capturedImage->getData());
    }

    public function testGuessMimeType(): void
    {
        $reflection = new \ReflectionClass($this->manager);
        $method = $reflection->getMethod('guessMimeType');

        $this->assertSame('image/jpeg', $method->invoke($this->manager, 'photo.jpg'));
        $this->assertSame('image/png', $method->invoke($this->manager, 'picture.png'));
        $this->assertSame('application/octet-stream', $method->invoke($this->manager, 'file.unknownext'));
    }

    public function testExtractAllImages(): void
    {
        $html = '<html>' .
            '<body>' .
            '<img src="image1.jpg">' .
            '<img src="https://example.com/image2.png">' .
            '<a href="file.pdf">Download</a>' .
            '</body>' .
            '</html>';

        $result = $this->manager->extractAllImages($html);

        $this->assertContains('image1.jpg', $result);
        $this->assertContains('https://example.com/image2.png', $result);
    }

    public function testDeleteTemplateImage(): void
    {
        $templateImage = $this->createMock(TemplateImage::class);

        $this->templateImageRepository->expects($this->once())
            ->method('remove')
            ->with($templateImage);

        $this->manager->delete($templateImage);
    }
}
