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

    public function embedTemplateImages(
        string $html,
        int $templateId,
        int $messageId,
    ): string {
        $extensions = implode('|', array_keys($this->mimeMap));
        $htmlImages = [];
        $filesystemImages = [];

        //# addition for external images
        if ($this->embedExternalImages) {
            $externalImages = [];
            $matchedImages = [];
            $pattern = sprintf(
                '~="(https?://(?!%s)([^"]+\.(%s))([\\?/][^"]+)?)"~Ui',
                preg_quote($this->configProvider->getValue(ConfigOption::Website)),
                $extensions
            );
            preg_match_all($pattern, $html, $matchedImages);

            for ($index = 0; $index < count($matchedImages[1]); ++$index) {
                if ($this->externalImageService->cache($matchedImages[1][$index], $messageId)) {
                    $externalImages[] = $matchedImages[1][$index]
                        . '~^~'
                        . basename($matchedImages[2][$index])
                        . '~^~'
                        . strtolower($matchedImages[3][$index]);
                }
            }

            if (!empty($externalImages)) {
                $externalImages = array_unique($externalImages);

                for ($index = 0; $index < count($externalImages); ++$index) {
                    $externalImage = explode('~^~', $externalImages[$index]);
                    $image = $this->externalImageService->getFromCache($externalImage[0], $messageId);
                    if ($image) {
                        $contentType = $this->mimeMap[$externalImage[2]];
                        $cid = $this->addHtmlImage($image, $externalImage[1], $contentType);

                        if (!empty($cid)) {
                            $html = str_replace($externalImage[0], 'cid:' . $cid, $html);
                        }
                    }
                }
            }
        }
        //# end addition

        preg_match_all('/"([^"]+\.('.$extensions.'))"/Ui', $html, $images);

        for ($i = 0; $i < count($images[1]); ++$i) {
            if ($this->getTemplateImage($templateId, $images[1][$i]) !== null) {
                $htmlImages[] = $images[1][$i];
                $html = str_replace($images[1][$i], basename($images[1][$i]), $html);
            }
            //# addition for filesystem images
            if ($this->embedUploadedImages) {
                if ($this->filesystemImageExists($images[1][$i])) {
                    $filesystemImages[] = $images[1][$i];
                    $html = str_replace($images[1][$i], basename($images[1][$i]), $html);
                }
            }
            //# end addition
        }
        if (!empty($htmlImages)) {
            // If duplicate images are embedded, they may show up as attachments, so remove them.
            $htmlImages = array_unique($htmlImages);
            sort($htmlImages);
            for ($i = 0; $i < count($htmlImages); ++$i) {
                if ($image = $this->getTemplateImage($templateId, $htmlImages[$i])) {
                    $content_type = $this->mimeMap[strtolower(substr($htmlImages[$i],  strrpos($htmlImages[$i], '.') + 1))];
                    $cid = $this->addHtmlImage($image->getData(), basename($htmlImages[$i]), $content_type);
                    if (!empty($cid)) {
                        $html = str_replace(basename($htmlImages[$i]), "cid:$cid", $html);
                    }
                }
            }
        }
        //# addition for filesystem images
        if (!empty($filesystemImages)) {
            // If duplicate images are embedded, they may show up as attachments, so remove them.
            $filesystemImages = array_unique($filesystemImages);
            sort($filesystemImages);
            for ($i = 0; $i < count($filesystemImages); ++$i) {
                if ($image = $this->getFilesystemImage($filesystemImages[$i])) {
                    $contentType = $this->mimeMap[strtolower(
                        substr($filesystemImages[$i],
                        strrpos($filesystemImages[$i], '.') + 1)
                    )];
                    $cid = $this->addHtmlImage($image, basename($filesystemImages[$i]), $contentType);
                    if (!empty($cid)) {
                        $html = str_replace(basename($filesystemImages[$i]), "cid:$cid", $html);
                    }
                }
            }
        }

        return $html;
    }

    public function getFilesystemImage(string $filename): string
    {
        //# get the image contents
        $localFile = basename(urldecode($filename));
        if ($this->uploadImagesDir) {
            $imageRoot = $this->configProvider->getValue(ConfigOption::UploadImageRoot);
            if (is_file($imageRoot.$localFile)) {
                return base64_encode(file_get_contents($imageRoot.$localFile));
            } else {
                if (is_file($this->documentRoot.$localFile)) {
                    //# save the document root to be able to retrieve the file later from commandline
                    $this->configManager->create(
                        ConfigOption::UploadImageRoot->value,
                        $this->documentRoot,
                        false,
                        'string',
                    );

                    return base64_encode(file_get_contents($this->documentRoot.$localFile));
                } elseif (is_file($this->documentRoot.'/'.$this->uploadImagesDir.'/image/'.$localFile)) {
                    $this->configManager->create(
                        ConfigOption::UploadImageRoot->value,
                        $this->documentRoot.'/'.$this->uploadImagesDir.'/image/',
                        false,
                        'string',
                    );

                    return base64_encode(file_get_contents($this->documentRoot.'/'.$this->uploadImagesDir.'/image/'.$localFile));
                } elseif (is_file($this->documentRoot.'/'.$this->uploadImagesDir.'/'.$localFile)) {
                    $this->configManager->create(
                        ConfigOption::UploadImageRoot->value,
                        $this->documentRoot.'/'.$this->uploadImagesDir.'/',
                        false,
                        'string',
                    );

                    return base64_encode(file_get_contents($this->documentRoot.'/'.$this->uploadImagesDir.'/'.$localFile));
                }
            }
        } elseif (is_file($this->documentRoot.$this->configProvider->getValue(ConfigOption::PageRoot).'/'.$this->editorImagesDir.'/'.$localFile)) {
            $elements = parse_url($filename);
            $localFile = basename($elements['path']);

            return base64_encode(file_get_contents($this->documentRoot.$this->configProvider->getValue(ConfigOption::PageRoot).'/'.$this->editorImagesDir.'/'.$localFile));
        } elseif (is_file($this->documentRoot.$this->configProvider->getValue(ConfigOption::PageRoot).'/'.$this->editorImagesDir.'/image/'.$localFile)) {
            return base64_encode(file_get_contents($this->documentRoot.$this->configProvider->getValue(ConfigOption::PageRoot).'/'.$this->editorImagesDir.'/image/'.$localFile));
        } elseif (is_file('../'.$this->editorImagesDir.'/'.$localFile)) {
            return base64_encode(file_get_contents('../'.$this->editorImagesDir.'/'.$localFile));
        } elseif (is_file('../'.$this->editorImagesDir.'/image/'.$localFile)) {
            return base64_encode(file_get_contents('../'.$this->editorImagesDir.'/image/'.$localFile));
        }

        return '';
    }

    public function addHtmlImage(string $contents, $name = '', $content_type = 'application/octet-stream'): string
    {
        $cid = bin2hex(random_bytes(16));
        $this->addStringEmbeddedImage(base64_decode($contents), $cid, $name, 'base64', $content_type);

        return $cid;
    }

    public function addStringEmbeddedImage(
        $string,
        $cid,
        $name = '',
        $encoding = 'base64',
        $type = '',
        $disposition = 'inline'
    ): bool {
        try {
            //If a MIME type is not specified, try to work it out from the name
            if ('' === $type && !empty($name)) {
                $type = mime_content_type($name);
            }

            if (ContentTransferEncoding::tryFrom($encoding) === null) {
                throw new Exception('encoding ' . $encoding);
            }

            //Append to $attachment array
            $this->attachment[] = [
                0 => $string,
                1 => $name,
                2 => $name,
                3 => $encoding,
                4 => $type,
                5 => true, //isStringAttachment
                6 => $disposition,
                7 => $cid,
            ];
        } catch (Exception $exc) {
            return false;
        }

        return true;
    }

    public function filesystemImageExists($filename): bool
    {
        //#  find the image referenced and see if it's on the server
        $imageRoot = $this->configProvider->getValue(ConfigOption::UploadImageRoot);

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
                is_file($this->documentRoot.$this->configProvider->getValue(ConfigOption::PageRoot).'/'.$this->editorImagesDir.'/image/'.$localFile)
                || is_file($this->documentRoot.$this->configProvider->getValue(ConfigOption::PageRoot).'/'.$this->editorImagesDir.'/'.$localFile)
                //# commandline
                || is_file('../'.$this->editorImagesDir.'/image/'.$localFile)
                || is_file('../'.$this->editorImagesDir.'/'.$localFile);
        }
    }

    public function getTemplateImage($templateId, $filename): ?TemplateImage
    {
        if (basename($filename) === 'powerphplist.png' || str_starts_with($filename, 'ORGANISATIONLOGO')) {
            $templateId = 0;
        }

        return $this->templateImageRepository->findByTemplateIdAndFilename($templateId, $filename);
    }
}
