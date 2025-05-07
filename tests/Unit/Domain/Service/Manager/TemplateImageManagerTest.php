<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Service\Manager;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Model\Messaging\Template;
use PhpList\Core\Domain\Model\Messaging\TemplateImage;
use PhpList\Core\Domain\Repository\Messaging\TemplateImageRepository;
use PhpList\Core\Domain\Service\Manager\TemplateImageManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TemplateImageManagerTest extends TestCase
{
    private TemplateImageRepository&MockObject $templateImageRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private TemplateImageManager $manager;

    protected function setUp(): void
    {
        $this->templateImageRepository = $this->createMock(TemplateImageRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->manager = new TemplateImageManager(
            $this->templateImageRepository,
            $this->entityManager
        );
    }

    public function testCreateImagesFromImagePaths(): void
    {
        $template = $this->createMock(Template::class);

        $this->entityManager->expects($this->exactly(2))
            ->method('persist')
            ->with($this->isInstanceOf(TemplateImage::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $images = $this->manager->createImagesFromImagePaths(['image1.jpg', 'image2.png'], $template);

        $this->assertCount(2, $images);
        foreach ($images as $image) {
            $this->assertInstanceOf(TemplateImage::class, $image);
        }
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

        $this->assertIsArray($result);
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
