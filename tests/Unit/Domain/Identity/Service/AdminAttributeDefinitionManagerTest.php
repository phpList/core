<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Identity\Service;

use PhpList\Core\Domain\Identity\Exception\AttributeDefinitionCreationException;
use PhpList\Core\Domain\Identity\Model\AdminAttributeDefinition;
use PhpList\Core\Domain\Identity\Model\Dto\AdminAttributeDefinitionDto;
use PhpList\Core\Domain\Identity\Repository\AdminAttributeDefinitionRepository;
use PhpList\Core\Domain\Identity\Service\Manager\AdminAttributeDefinitionManager;
use PhpList\Core\Domain\Identity\Validator\AttributeTypeValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminAttributeDefinitionManagerTest extends TestCase
{
    private AdminAttributeDefinitionRepository&MockObject $repository;
    private AdminAttributeDefinitionManager $subject;
    private TranslatorInterface&MockObject $translator;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AdminAttributeDefinitionRepository::class);
        $attributeTypeValidator = $this->createMock(AttributeTypeValidator::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->subject = new AdminAttributeDefinitionManager(
            definitionRepository: $this->repository,
            attributeTypeValidator: $attributeTypeValidator,
            translator: $this->translator,
        );
    }

    public function testCreateCreatesNewAttributeDefinition(): void
    {
        $dto = new AdminAttributeDefinitionDto(
            name: 'test-attribute',
            type: 'text',
            listOrder: 10,
            defaultValue: 'default',
            required: true,
        );

        $this->repository->expects($this->once())
            ->method('findOneByName')
            ->with('test-attribute')
            ->willReturn(null);

        $this->repository->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (AdminAttributeDefinition $definition) use ($dto) {
                return $definition->getName() === $dto->name
                    && $definition->getType() === $dto->type
                    && $definition->getListOrder() === $dto->listOrder
                    && $definition->getDefaultValue() === $dto->defaultValue
                    && $definition->isRequired() === $dto->required;
            }));

        $result = $this->subject->create($dto);

        $this->assertInstanceOf(AdminAttributeDefinition::class, $result);
        $this->assertEquals($dto->name, $result->getName());
        $this->assertEquals($dto->type, $result->getType());
        $this->assertEquals($dto->listOrder, $result->getListOrder());
        $this->assertEquals($dto->defaultValue, $result->getDefaultValue());
        $this->assertEquals($dto->required, $result->isRequired());
    }

    public function testCreateThrowsExceptionIfAttributeAlreadyExists(): void
    {
        $dto = new AdminAttributeDefinitionDto(
            name: 'test-attribute'
        );

        $existingAttribute = $this->createMock(AdminAttributeDefinition::class);

        $this->repository->expects($this->once())
            ->method('findOneByName')
            ->with('test-attribute')
            ->willReturn($existingAttribute);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('Attribute definition already exists.')
            ->willReturn('Attribute definition already exists.');

        $this->expectException(AttributeDefinitionCreationException::class);
        $this->expectExceptionMessage('Attribute definition already exists');

        $this->subject->create($dto);
    }

    public function testUpdateUpdatesAttributeDefinition(): void
    {
        $attributeDefinition = $this->createMock(AdminAttributeDefinition::class);
        $attributeDefinition->method('getId')->willReturn(1);

        $dto = new AdminAttributeDefinitionDto(
            name: 'updated-attribute',
            type: 'checkbox',
            listOrder: 20,
            defaultValue: 'new-default',
            required: false,
        );

        $this->repository->expects($this->once())
            ->method('findOneByName')
            ->with('updated-attribute')
            ->willReturn(null);

        $attributeDefinition->expects($this->once())
            ->method('setName')
            ->with('updated-attribute')
            ->willReturnSelf();

        $attributeDefinition->expects($this->once())
            ->method('setType')
            ->with('checkbox')
            ->willReturnSelf();

        $attributeDefinition->expects($this->once())
            ->method('setListOrder')
            ->with(20)
            ->willReturnSelf();

        $attributeDefinition->expects($this->once())
            ->method('setDefaultValue')
            ->with('new-default')
            ->willReturnSelf();

        $attributeDefinition->expects($this->once())
            ->method('setRequired')
            ->with(false)
            ->willReturnSelf();

        $result = $this->subject->update($attributeDefinition, $dto);

        $this->assertSame($attributeDefinition, $result);
    }

    public function testUpdateThrowsExceptionIfAnotherAttributeWithSameNameExists(): void
    {
        $attributeDefinition = $this->createMock(AdminAttributeDefinition::class);
        $attributeDefinition->method('getId')->willReturn(1);

        $dto = new AdminAttributeDefinitionDto(
            name: 'existing-attribute'
        );

        $existingAttribute = $this->createMock(AdminAttributeDefinition::class);
        $existingAttribute->method('getId')->willReturn(2);

        $this->repository->expects($this->once())
            ->method('findOneByName')
            ->with('existing-attribute')
            ->willReturn($existingAttribute);

        $this->expectException(AttributeDefinitionCreationException::class);
        $this->expectExceptionMessage('Another attribute with this name already exists.');

        $this->subject->update($attributeDefinition, $dto);
    }

    public function testDeleteCallsRemoveOnRepository(): void
    {
        $attributeDefinition = $this->createMock(AdminAttributeDefinition::class);

        $this->repository->expects($this->once())
            ->method('remove')
            ->with($attributeDefinition);

        $this->subject->delete($attributeDefinition);
    }

    public function testGetTotalCountReturnsCountFromRepository(): void
    {
        $this->repository->expects($this->once())
            ->method('count')
            ->willReturn(42);

        $result = $this->subject->getTotalCount();

        $this->assertEquals(42, $result);
    }

    public function testGetAttributesAfterIdReturnsAttributesFromRepository(): void
    {
        $afterId = 10;
        $limit = 20;
        $attributes = [
            $this->createMock(AdminAttributeDefinition::class),
            $this->createMock(AdminAttributeDefinition::class),
        ];

        $this->repository->expects($this->once())
            ->method('getAfterId')
            ->with($afterId, $limit)
            ->willReturn($attributes);

        $result = $this->subject->getAttributesAfterId($afterId, $limit);

        $this->assertSame($attributes, $result);
    }
}
