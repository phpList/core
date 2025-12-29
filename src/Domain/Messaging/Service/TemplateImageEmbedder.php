<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use Exception;
use PhpList\Core\Domain\Common\ExternalImageService;
use PhpList\Core\Domain\Common\Model\ContentTransferEncoding;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Manager\ConfigManager;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Model\TemplateImage;
use PhpList\Core\Domain\Messaging\Repository\TemplateImageRepository;

class TemplateImageEmbedder
{

    /** @var array<string,string> extension => mime */
    private array $mimeMap = [
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
    public array $attachment = [];

    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly ConfigManager $configManager,
        private readonly ExternalImageService $externalImageService,
        private readonly TemplateImageRepository $templateImageRepository,
        private readonly string $documentRoot,
        private readonly string $editorImagesDir,
        private readonly bool $embedExternalImages = false,
        private readonly bool $embedUploadedImages = false,
        private readonly ?string $uploadImagesDir = null,
    ) {
    }

    public function __invoke(string $html, int $messageId): string
    {
        $templateId = (int)$this->configProvider->getValue(ConfigOption::SystemMessageTemplate);
        $extensions = implode('|', array_keys($this->mimeMap));

        if ($this->embedExternalImages) {
            $html = $this->embedExternalImages($html, $messageId, $extensions);
        }

        preg_match_all('/"([^"]+\.('.$extensions.'))"/Ui', $html, $images);
        $htmlImages = [];
        $filesystemImages = [];
        foreach ($images[1] as $img) {
            if ($this->getTemplateImage($templateId, $img) !== null) {
                $htmlImages[] = $img;
                $html = str_replace($img, basename($img), $html);
            }
            if ($this->embedUploadedImages && $this->filesystemImageExists($img)) {
                $filesystemImages[] = $img;
                $html = str_replace($img, basename($img), $html);
            }
        }

        if (!empty($htmlImages)) {
            $html = $this->embedTemplateImages($html, $templateId, $htmlImages);
        }

        if (!empty($filesystemImages)) {
            $html = $this->embedFilesystemImages($html, $filesystemImages);
        }

        return $html;
    }

    private function getFilesystemImage(string $filename): string
    {
        $localFile = basename(urldecode($filename));
        $pageRoot = $this->configProvider->getValue(ConfigOption::PageRoot);
        $candidates = [];

        if ($this->uploadImagesDir) {
            $imageRoot = $this->configProvider->getValue(ConfigOption::UploadImageRoot);
            $candidates[] = [
                'path' => $imageRoot . $localFile,
                'config' => null,
            ];

            $candidates[] = [
                'path' => $this->documentRoot . $localFile,
                'config' => $this->documentRoot,
            ];

            $candidates[] = [
                'path' => $this->documentRoot . '/' . $this->uploadImagesDir . '/image/' . $localFile,
                'config' => $this->documentRoot . '/' . $this->uploadImagesDir . '/image/',
            ];

            $candidates[] = [
                'path' => $this->documentRoot . '/' . $this->uploadImagesDir . '/' . $localFile,
                'config' => $this->documentRoot . '/' . $this->uploadImagesDir . '/',
            ];
        } else {
            $elements = parse_url($filename);
            $parsedFile = basename($elements['path'] ?? $localFile);

            $candidates[] = [
                'path' => $this->documentRoot . $pageRoot . '/' . $this->editorImagesDir . '/' . $parsedFile,
                'config' => null,
            ];

            $candidates[] = [
                'path' => $this->documentRoot . $pageRoot . '/' . $this->editorImagesDir . '/image/' . $parsedFile,
                'config' => null,
            ];

            $candidates[] = [
                'path' => '../' . $this->editorImagesDir . '/' . $parsedFile,
                'config' => null,
            ];

            $candidates[] = [
                'path' => '../' . $this->editorImagesDir . '/image/' . $parsedFile,
                'config' => null,
            ];
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate['path'])) {
                if ($candidate['config'] !== null) {
                    $this->configManager->create(
                        ConfigOption::UploadImageRoot->value,
                        $candidate['config'],
                        false,
                        'string'
                    );
                }

                return base64_encode(file_get_contents($candidate['path']));
            }
        }

        return '';
    }

    private function addHtmlImage(string $contents, $name = '', $contentType = 'application/octet-stream'): string
    {
        $cid = bin2hex(random_bytes(16));
        $this->addStringEmbeddedImage(base64_decode($contents), $cid, $name, 'base64', $contentType);

        return $cid;
    }

    /**
     * @throws Exception
     */
    private function addStringEmbeddedImage(
        $string,
        $cid,
        $name = '',
        $encoding = 'base64',
        $type = '',
        $disposition = 'inline'
    ): void {
        //If a MIME type is not specified, try to work it out from the name
        if ('' === $type && !empty($name)) {
            $type = mime_content_type($name);
        }

        if (ContentTransferEncoding::tryFrom($encoding) === null) {
            throw new Exception('encoding ' . $encoding);
        }

        $this->attachment[] = [
            0 => $string,
            1 => $name,
            2 => $name,
            3 => $encoding,
            4 => $type,
            5 => true,
            6 => $disposition,
            7 => $cid,
        ];
    }

    private function filesystemImageExists($filename): bool
    {
        //#  find the image referenced and see if it's on the server
        $imageRoot = $this->configProvider->getValue(ConfigOption::UploadImageRoot);
        $pageRoot = $this->configProvider->getValue(ConfigOption::UploadImageRoot);

        $elements = parse_url($filename);
        $localFile = basename($elements['path']);
        $localFile = urldecode($localFile);

        if ($this->uploadImagesDir) {
            return
                is_file($this->documentRoot.'/'.$this->uploadImagesDir.'/image/'.$localFile)
                || is_file($this->documentRoot.'/'.$this->uploadImagesDir.'/'.$localFile)
                //# commandline
                || is_file($imageRoot.'/'.$localFile);
        } else {
            return
                is_file($this->documentRoot . $pageRoot . '/' . $this->editorImagesDir . '/image/' . $localFile)
                || is_file($pageRoot . '/' . $this->editorImagesDir . '/' . $localFile)
                //# commandline
                || is_file('../' . $this->editorImagesDir . '/image/' . $localFile)
                || is_file('../' . $this->editorImagesDir . '/' . $localFile);
        }
    }

    private function getTemplateImage($templateId, $filename): ?TemplateImage
    {
        if (basename($filename) === 'powerphplist.png' || str_starts_with($filename, 'ORGANISATIONLOGO')) {
            $templateId = 0;
        }

        return $this->templateImageRepository->findByTemplateIdAndFilename($templateId, $filename);
    }

    private function embedExternalImages(string $html, int $messageId, string $extensions): string
    {
        $externalImages = [];
        $matchedImages = [];
        $pattern = sprintf(
            '~="(https?://(?!%s)([^"]+\.(%s))([\\?/][^"]+)?)"~Ui',
            preg_quote($this->configProvider->getValue(ConfigOption::Website)),
            $extensions
        );
        preg_match_all($pattern, $html, $matchedImages);

        foreach ($matchedImages[1] as $index => $url) {
            if ($this->externalImageService->cache($url, $messageId)) {
                $externalImages[] = $url
                    . '~^~'
                    . basename($matchedImages[2][$index])
                    . '~^~'
                    . strtolower($matchedImages[3][$index]);
            }
        }

        if (!empty($externalImages)) {
            $externalImages = array_unique($externalImages);

            foreach ($externalImages as $file) {
                $externalImage = explode('~^~', $file);
                $image = $this->externalImageService->getFromCache($externalImage[0], $messageId);
                if ($image) {
                    $contentType = $this->mimeMap[$externalImage[2]];
                    $cid = $this->addHtmlImage($image, $externalImage[1], $contentType);

                    if (!empty($cid)) {
                        $html = str_replace($externalImage[0], sprintf('cid:%s', $cid), $html);
                    }
                }
            }
        }

        return $html;
    }

    private function embedTemplateImages(string $html, int $templateId, array $htmlImages): string
    {
        // If duplicate images are embedded, they may show up as attachments, so remove them.
        $htmlImages = array_unique($htmlImages);
        sort($htmlImages);
        foreach ($htmlImages as $file) {
            $image = $this->getTemplateImage($templateId, $file);
            if ($image) {
                $contentType = $this->mimeMap[strtolower(
                    substr($file, strrpos($file, '.') + 1)
                )];
                $cid = $this->addHtmlImage($image->getData(), basename($file), $contentType);
                if (!empty($cid)) {
                    $html = str_replace(basename($file), sprintf('cid:%s', $cid), $html);
                }
            }
        }

        return $html;
    }

    private function embedFilesystemImages(array|string $html, array $filesystemImages): string
    {
        // If duplicate images are embedded, they may show up as attachments, so remove them.
        $filesystemImages = array_unique($filesystemImages);
        sort($filesystemImages);
        foreach ($filesystemImages as $file) {
            $image = $this->getFilesystemImage($file);
            if ($image) {
                $contentType = $this->mimeMap[strtolower(
                    substr($file, strrpos($file, '.') + 1)
                )];
                $cid = $this->addHtmlImage($image, basename($file), $contentType);
                if (!empty($cid)) {
                    $html = str_replace(basename($file), sprintf('cid:%s', $cid), $html);
                }
            }
        }

        return $html;
    }
}
