<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Manager;

use PhpList\Core\Domain\Messaging\Model\Dto\CreateTemplateDto;
use PhpList\Core\Domain\Messaging\Model\Dto\UpdateTemplateDto;
use PhpList\Core\Domain\Messaging\Model\Template;
use PhpList\Core\Domain\Messaging\Repository\TemplateRepository;
use PhpList\Core\Domain\Messaging\Service\Manager\TemplateImageManager;
use PhpList\Core\Domain\Messaging\Service\Manager\TemplateManager;
use PhpList\Core\Domain\Messaging\Validator\TemplateImageValidator;
use PhpList\Core\Domain\Messaging\Validator\TemplateLinkValidator;
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
        $this->templateImageManager = $this->createMock(TemplateImageManager::class);
        $this->templateLinkValidator = $this->createMock(TemplateLinkValidator::class);
        $this->templateImageValidator = $this->createMock(TemplateImageValidator::class);

        $this->manager = new TemplateManager(
            templateRepository: $this->templateRepository,
            templateImageManager: $this->templateImageManager,
            templateLinkValidator: $this->templateLinkValidator,
            templateImageValidator: $this->templateImageValidator
        );
    }

    public function testUpdateTemplateSuccessfully(): void
    {
        $existing = (new Template('Old Title'))
            ->setContent('<html><body>Old</body></html>')
            ->setText(null);

        $dto = new UpdateTemplateDto(
            title: 'New Title',
            content: '<html><body>New</body></html>',
            text: 'New text',
            fileContent: null,
            shouldCheckLinks: true,
            shouldCheckImages: true,
            shouldCheckExternalImages: true,
        );

        $this->templateLinkValidator->expects($this->once())
            ->method('validate')
            ->with($dto->content, $this->anything());

        $this->templateImageManager->expects($this->once())
            ->method('extractAllImages')
            ->with($dto->content)
            ->willReturn(['a.png']);

        $this->templateImageValidator->expects($this->once())
            ->method('validate')
            ->with(['a.png'], $this->anything());

        $this->templateImageManager->expects($this->once())
            ->method('createImagesFromImagePaths')
            ->with(['a.png'], $this->isInstanceOf(Template::class));

        $updated = $this->manager->update(template: $existing, updateTemplateDto: $dto);

        $this->assertSame('New Title', $updated->getTitle());
        $this->assertSame('New text', $updated->getText());
        $this->assertSame($dto->content, $updated->getContent());
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
            ->method('persist')
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
