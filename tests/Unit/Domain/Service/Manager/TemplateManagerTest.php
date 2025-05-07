<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Service\Manager;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Model\Messaging\Dto\CreateTemplateDto;
use PhpList\Core\Domain\Model\Messaging\Template;
use PhpList\Core\Domain\Repository\Messaging\TemplateRepository;
use PhpList\Core\Domain\Service\Manager\TemplateImageManager;
use PhpList\Core\Domain\Service\Manager\TemplateManager;
use PhpList\Core\Domain\Service\Validator\TemplateImageValidator;
use PhpList\Core\Domain\Service\Validator\TemplateLinkValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TemplateManagerTest extends TestCase
{
    private TemplateRepository&MockObject $templateRepository;
    private TemplateImageManager&MockObject $templateImageManager;
    private TemplateLinkValidator&MockObject $templateLinkValidator;
    private TemplateImageValidator&MockObject $templateImageValidator;
    private TemplateManager $manager;

    protected function setUp(): void
    {
        $this->templateRepository = $this->createMock(TemplateRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $this->templateImageManager = $this->createMock(TemplateImageManager::class);
        $this->templateLinkValidator = $this->createMock(TemplateLinkValidator::class);
        $this->templateImageValidator = $this->createMock(TemplateImageValidator::class);

        $this->manager = new TemplateManager(
            $this->templateRepository,
            $entityManager,
            $this->templateImageManager,
            $this->templateLinkValidator,
            $this->templateImageValidator
        );
    }

    public function testCreateTemplateSuccessfully(): void
    {
        $request = new CreateTemplateDto(
            title: 'Test Template',
            content: '<html><body>Content</body></html>',
            text: 'Plain text',
            fileContent: null,
            shouldCheckLinks: true,
            shouldCheckImages: true,
            shouldCheckExternalImages: true
        );

        $this->templateLinkValidator->expects($this->once())
            ->method('validate')
            ->with($request->content, $this->anything());

        $this->templateImageManager->expects($this->once())
            ->method('extractAllImages')
            ->with($request->content)
            ->willReturn([]);

        $this->templateImageValidator->expects($this->once())
            ->method('validate')
            ->with([], $this->anything());

        $this->templateRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Template::class));

        $this->templateImageManager->expects($this->once())
            ->method('createImagesFromImagePaths')
            ->with([], $this->isInstanceOf(Template::class));

        $template = $this->manager->create($request);

        $this->assertSame('Test Template', $template->getTitle());
    }

    public function testDeleteTemplate(): void
    {
        $template = $this->createMock(Template::class);

        $this->templateRepository->expects($this->once())
            ->method('remove')
            ->with($template);

        $this->manager->delete($template);
    }
}
