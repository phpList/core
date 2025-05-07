<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Service\Manager;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Model\Dto\ValidationContext;
use PhpList\Core\Domain\Model\Messaging\Dto\CreateTemplateDto;
use PhpList\Core\Domain\Model\Messaging\Template;
use PhpList\Core\Domain\Model\Subscription\Dto\UpdateSubscriberDto;
use PhpList\Core\Domain\Model\Subscription\Subscriber;
use PhpList\Core\Domain\Repository\Messaging\TemplateRepository;
use PhpList\Core\Domain\Service\Validator\TemplateImageValidator;
use PhpList\Core\Domain\Service\Validator\TemplateLinkValidator;

class TemplateManager
{
    private TemplateRepository $templateRepository;
    private EntityManagerInterface $entityManager;
    private TemplateImageManager $templateImageManager;
    private TemplateLinkValidator $templateLinkValidator;
    private TemplateImageValidator $templateImageValidator;

    public function __construct(
        TemplateRepository $templateRepository,
        EntityManagerInterface $entityManager,
        TemplateImageManager $templateImageManager,
        TemplateLinkValidator $templateLinkValidator,
        TemplateImageValidator $templateImageValidator
    ) {
        $this->templateRepository = $templateRepository;
        $this->entityManager = $entityManager;
        $this->templateImageManager = $templateImageManager;
        $this->templateLinkValidator = $templateLinkValidator;
        $this->templateImageValidator = $templateImageValidator;
    }

    public function create(CreateTemplateDto $createTemplateDto): Template
    {
        $template = (new Template($createTemplateDto->title))
            ->setContent($createTemplateDto->content)
            ->setText($createTemplateDto->text);

        if ($createTemplateDto->fileContent) {
            $template->setContent($createTemplateDto->fileContent);
        }

        $context = (new ValidationContext())
            ->set('checkLinks', $createTemplateDto->shouldCheckLinks)
            ->set('checkImages', $createTemplateDto->shouldCheckImages)
            ->set('checkExternalImages', $createTemplateDto->shouldCheckExternalImages);

        $this->templateLinkValidator->validate($template->getContent() ?? '', $context);

        $imageUrls = $this->templateImageManager->extractAllImages($template->getContent() ?? '');
        $this->templateImageValidator->validate($imageUrls, $context);

        $this->templateRepository->save($template);

        $this->templateImageManager->createImagesFromImagePaths($imageUrls, $template);

        return $template;
    }

    public function update(UpdateSubscriberDto $updateSubscriberDto): Subscriber
    {
        /** @var Subscriber $subscriber */
        $subscriber = $this->templateRepository->find($updateSubscriberDto->subscriberId);

        $subscriber->setEmail($updateSubscriberDto->email);
        $subscriber->setConfirmed($updateSubscriberDto->confirmed);
        $subscriber->setBlacklisted($updateSubscriberDto->blacklisted);
        $subscriber->setHtmlEmail($updateSubscriberDto->htmlEmail);
        $subscriber->setDisabled($updateSubscriberDto->disabled);
        $subscriber->setExtraData($updateSubscriberDto->additionalData);

        $this->entityManager->flush();

        return $subscriber;
    }

    public function delete(Template $template): void
    {
        $this->templateRepository->remove($template);
    }
}
