<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Manager;

use DOMDocument;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
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

    public function __construct(
        private readonly TemplateImageRepository $templateImageRepository,
        private readonly ConfigProvider $configProvider,
    ) {
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

            $this->templateImageRepository->persist($image);
            $templateImages[] = $image;
        }

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

    public function parseLogoPlaceholders(string $content): string
    {
        //# replace Logo placeholders
        preg_match_all('/\[LOGO\:?(\d+)?\]/', $content, $logoInstances);
        foreach ($logoInstances[0] as $index => $logoInstance) {
            $size = sprintf('%d', $logoInstances[1][$index]);
            $logoSize = !empty($size) ? $size : '500';
            $this->createCachedLogoImage((int)$logoSize);
            $content = str_replace($logoInstance, 'ORGANISATIONLOGO'.$logoSize.'.png', $content);
        }

        return $content;
    }

    public function createCachedLogoImage(int $size): void
    {
        $logoImageId = $this->configProvider->getValue(ConfigOption::OrganisationLogo);
        if (empty($logoImageId)) {
            return;
        }

        $orgLogoImage = $this->templateImageRepository->findByFilename(sprintf('ORGANISATIONLOGO%s.png', $size));
        if ($orgLogoImage !== null && !empty($orgLogoImage->getData())) {
            return;
        }

        $logoImage = $this->templateImageRepository->findById((int) $logoImageId);
        if ($logoImage === null) {
            return;
        }

        $imageContent = $this->decodeLogoImageData($logoImage->getData());
        if ($imageContent === null) {
            return;
        }

        $imgSize = getimagesizefromstring($imageContent);
        if ($imgSize === false) {
            return;
        }

        [$newWidth, $newHeight, $sizeFactor] = $this->calculateDimensions($imgSize, $size);

        $imageContent = $this->resizeImageIfNecessary(
            $imageContent,
            $imgSize,
            $newWidth,
            $newHeight,
            $sizeFactor,
            $orgLogoImage
        );

        // else copy original
        $templateImage = (new TemplateImage())
            ->setFilename('ORGANISATIONLOGO' . $size . '.png')
            ->setMimetype($imgSize['mime'])
            ->setData(base64_encode($imageContent))
            ->setWidth($newWidth)
            ->setHeight($newHeight);

        $this->templateImageRepository->persist($templateImage);
    }

    private function decodeLogoImageData(?string $logoData): ?string
    {
        $imageContent = base64_decode($logoData ?? '', true);

        if (!empty($imageContent)) {
            return $imageContent;
        }

        $fallback = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQMAAAAl21bKAAAABGdBTUEAALGPC/'
         . 'xhBQAAAAZQTFRF////AAAAVcLTfgAAAAF0Uk5TAEDm2GYAAAABYktHRACIBR1IAAAACXBIWXMAAAsSAAALEgHS3X78'
         . 'AAAAB3RJTUUH0gQCEx05cqKA8gAAAApJREFUeJxjYAAAAAIAAUivpHEAAAAASUVORK5CYII=');

        $fallbackContent = base64_decode($fallback, true);

        return $fallbackContent !== false ? $fallbackContent : null;
    }

    private function calculateDimensions(array $imgSize, int $size): array
    {
        $sizeW = $imgSize[0];
        $sizeH = $imgSize[1];
        if ($sizeH > $sizeW) {
            $sizeFactor = (float) ($size / $sizeH);
        } else {
            $sizeFactor = (float) ($size / $sizeW);
        }
        $newWidth = (int) ($sizeW * $sizeFactor);
        $newHeight = (int) ($sizeH * $sizeFactor);

        return [$newWidth, $newHeight, $sizeFactor];
    }

    private function resizeImageIfNecessary(
        string $imageContent,
        array $imgSize,
        mixed $newWidth,
        mixed $newHeight,
        mixed $sizeFactor,
        ?TemplateImage $orgLogoImage
    ): string {
        if ($sizeFactor >= 1) {
            return $imageContent;
        }

        $original = imagecreatefromstring($imageContent);
        //# creates a black image (why would you want that....)
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagesavealpha($resized, true);
        //# white. All the methods to make it transparent didn't work for me @@TODO really make transparent
        $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
        imagefill($resized, 0, 0, $transparent);
        [$sizeW, $sizeH] = [$imgSize[0], $imgSize[1]];

        if (imagecopyresized(
            $resized,
            $original,
            0,
            0,
            0,
            0,
            $newWidth,
            $newHeight,
            $sizeW,
            $sizeH,
        )) {
            if ($orgLogoImage !== null) {
                $this->templateImageRepository->remove($orgLogoImage);
            }

            //# rather convoluted way to get the image contents
            $buffer = ob_get_contents();
            ob_end_clean();
            ob_start();
            imagepng($resized);
            $imageContent = ob_get_contents();
            ob_end_clean();
            echo $buffer;
        }

        return $imageContent;
    }
}
