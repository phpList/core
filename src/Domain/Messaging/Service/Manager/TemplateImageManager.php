<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Manager;

use Doctrine\ORM\EntityManagerInterface;
use DOMDocument;
use PhpList\Core\Domain\Messaging\Model\Template;
use PhpList\Core\Domain\Messaging\Model\TemplateImage;
use PhpList\Core\Domain\Messaging\Repository\TemplateImageRepository;

class TemplateImageManager
{
    public const IMAGE_MIME_TYPES = [
        'gif'  => 'image/gif',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'jpe'  => 'image/jpeg',
        'bmp'  => 'image/bmp',
        'png'  => 'image/png',
        'tif'  => 'image/tiff',
        'tiff' => 'image/tiff',
        'swf'  => 'application/x-shockwave-flash',
    ];

    private TemplateImageRepository $templateImageRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(
        TemplateImageRepository $templateImageRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->templateImageRepository = $templateImageRepository;
        $this->entityManager = $entityManager;
    }

    /** @return TemplateImage[] */
    public function createImagesFromImagePaths(array $imagePaths, Template $template): array
    {
        $templateImages = [];
        foreach ($imagePaths as $path) {
            $image = new TemplateImage();
            $image->setTemplate($template);
            $image->setFilename($path);
            $image->setMimeType($this->guessMimeType($path));
            $image->setData(null);

            $this->entityManager->persist($image);
            $templateImages[] = $image;
        }

        $this->entityManager->flush();

        return $templateImages;
    }

    private function guessMimeType(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return self::IMAGE_MIME_TYPES[$ext] ?? 'application/octet-stream';
    }

    public function extractAllImages(string $html): array
    {
        $fromRegex = array_keys(
            $this->extractTemplateImagesFromContent($html)
        );

        $fromDom = $this->extractImagesFromHtml($html);

        return array_values(array_unique(array_merge($fromRegex, $fromDom)));
    }

    private function extractTemplateImagesFromContent(string $content): array
    {
        $regexp = sprintf('/"([^"]+\.(%s))"/Ui', implode('|', array_keys(self::IMAGE_MIME_TYPES)));
        preg_match_all($regexp, stripslashes($content), $images);

        return array_count_values($images[1]);
    }

    private function extractImagesFromHtml(string $html): array
    {
        $dom = new DOMDocument();
        // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        @$dom->loadHTML($html);
        $images = [];

        foreach ($dom->getElementsByTagName('img') as $img) {
            $src = $img->getAttribute('src');
            if ($src) {
                $images[] = $src;
            }
        }

        return $images;
    }

    public function delete(TemplateImage $templateImage): void
    {
        $this->templateImageRepository->remove($templateImage);
    }
}
