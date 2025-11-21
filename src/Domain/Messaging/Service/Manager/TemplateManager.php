<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Manager;

use PhpList\Core\Domain\Common\Model\ValidationContext;
use PhpList\Core\Domain\Messaging\Model\Dto\CreateTemplateDto;
use PhpList\Core\Domain\Messaging\Model\Dto\UpdateTemplateDto;
use PhpList\Core\Domain\Messaging\Model\Template;
use PhpList\Core\Domain\Messaging\Repository\TemplateRepository;
use PhpList\Core\Domain\Messaging\Validator\TemplateImageValidator;
use PhpList\Core\Domain\Messaging\Validator\TemplateLinkValidator;

class TemplateManager
{
    private TemplateRepository $templateRepository;
    private TemplateImageManager $templateImageManager;
    private TemplateLinkValidator $templateLinkValidator;
    private TemplateImageValidator $templateImageValidator;

    public function __construct(
        TemplateRepository $templateRepository,
        TemplateImageManager $templateImageManager,
        TemplateLinkValidator $templateLinkValidator,
        TemplateImageValidator $templateImageValidator
    ) {
        $this->templateRepository = $templateRepository;
        $this->templateImageManager = $templateImageManager;
        $this->templateLinkValidator = $templateLinkValidator;
        $this->templateImageValidator = $templateImageValidator;
    }

    public function create(CreateTemplateDto $createTemplateDto): Template
    {
        $template = (new Template($createTemplateDto->title))
            ->setText($createTemplateDto->text);

        $content = $createTemplateDto->fileContent ?? $createTemplateDto->content;
        if ($content !== null) {
            $template->setContent($content);
        }

        $context = (new ValidationContext())
            ->set('checkLinks', $createTemplateDto->shouldCheckLinks)
            ->set('checkImages', $createTemplateDto->shouldCheckImages)
            ->set('checkExternalImages', $createTemplateDto->shouldCheckExternalImages);

        $this->templateLinkValidator->validate($template->getContent() ?? '', $context);

        $imageUrls = $this->templateImageManager->extractAllImages($template->getContent() ?? '');
        $this->templateImageValidator->validate($imageUrls, $context);

        $this->templateRepository->persist($template);

        $this->templateImageManager->createImagesFromImagePaths($imageUrls, $template);

        return $template;
    }

    public function update(Template $template, UpdateTemplateDto $updateTemplateDto): Template
    {
        if ($updateTemplateDto->title !== null) {
            $template->setTitle($updateTemplateDto->title);
        }

        if ($updateTemplateDto->text !== null) {
            $template->setText($updateTemplateDto->text);
        }

        $content = $updateTemplateDto->fileContent ?? $updateTemplateDto->content;
        if ($content !== null) {
            $template->setContent($content);
        }

        $context = (new ValidationContext())
            ->set('checkLinks', $updateTemplateDto->shouldCheckLinks)
            ->set('checkImages', $updateTemplateDto->shouldCheckImages)
            ->set('checkExternalImages', $updateTemplateDto->shouldCheckExternalImages);

        $this->templateLinkValidator->validate($template->getContent() ?? '', $context);

        $imageUrls = $this->templateImageManager->extractAllImages($template->getContent() ?? '');
        $this->templateImageValidator->validate($imageUrls, $context);

        foreach ($template->getImages() as $image) {
            $this->templateImageManager->delete($image);
        }

        $this->templateImageManager->createImagesFromImagePaths($imageUrls, $template);

        return $template;
    }

    public function delete(Template $template): void
    {
        $this->templateRepository->remove($template);
    }
}
