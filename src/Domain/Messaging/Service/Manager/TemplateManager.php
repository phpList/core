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
    public function __construct(
        private readonly TemplateRepository $templateRepository,
        private readonly TemplateImageManager $templateImageManager,
        private readonly TemplateLinkValidator $templateLinkValidator,
        private readonly TemplateImageValidator $templateImageValidator
    ) {
    }

    public function create(CreateTemplateDto $createTemplateDto): Template
    {
        $template = (new Template(title: $createTemplateDto->title))
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
        $this->templateImageValidator->validate(value: $imageUrls, context: $context);

        $this->templateRepository->persist($template);

        $this->templateImageManager->createImagesFromImagePaths(imagePaths: $imageUrls, template: $template);

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

        $this->templateImageManager->createImagesFromImagePaths($imageUrls, $template);

        return $template;
    }

    public function delete(Template $template): void
    {
        $this->templateRepository->remove($template);
    }
}
