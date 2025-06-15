<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service;

use PhpList\Core\Domain\Subscription\Exception\AttributeDefinitionCreationException;
use PhpList\Core\Domain\Subscription\Model\Dto\AttributeDefinitionDto;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeDefinitionRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\AttributeDefinitionManager;
use PhpList\Core\Domain\Subscription\Validator\AttributeTypeValidator;
use PHPUnit\Framework\TestCase;

class AttributeDefinitionManagerTest extends TestCase
{
    public function testCreateAttributeDefinition(): void
    {
        $repository = $this->createMock(SubscriberAttributeDefinitionRepository::class);
        $validator = $this->createMock(AttributeTypeValidator::class);
        $manager = new AttributeDefinitionManager($repository, $validator);

        $dto = new AttributeDefinitionDto(
            name: 'Country',
            type: 'checkbox',
            listOrder: 1,
            defaultValue: 'US',
            required: true,
            tableName: 'user_attribute'
        );

        $repository->expects($this->once())
            ->method('findOneByName')
            ->with('Country')
            ->willReturn(null);

        $repository->expects($this->once())->method('save');

        $attribute = $manager->create($dto);

        $this->assertInstanceOf(SubscriberAttributeDefinition::class, $attribute);
        $this->assertSame('Country', $attribute->getName());
        $this->assertSame('checkbox', $attribute->getType());
        $this->assertSame(1, $attribute->getListOrder());
        $this->assertSame('US', $attribute->getDefaultValue());
        $this->assertTrue($attribute->isRequired());
        $this->assertSame('user_attribute', $attribute->getTableName());
    }

    public function testCreateThrowsWhenAttributeAlreadyExists(): void
    {
        $repository = $this->createMock(SubscriberAttributeDefinitionRepository::class);
        $validator = $this->createMock(AttributeTypeValidator::class);
        $manager = new AttributeDefinitionManager($repository, $validator);

        $dto = new AttributeDefinitionDto(
            name: 'Country',
            type: 'checkbox',
            listOrder: 1,
            defaultValue: 'US',
            required: true,
            tableName: 'user_attribute'
        );

        $existing = $this->createMock(SubscriberAttributeDefinition::class);

        $repository->expects($this->once())
            ->method('findOneByName')
            ->with('Country')
            ->willReturn($existing);

        $this->expectException(AttributeDefinitionCreationException::class);

        $manager->create($dto);
    }

    public function testUpdateAttributeDefinition(): void
    {
        $repository = $this->createMock(SubscriberAttributeDefinitionRepository::class);
        $validator = $this->createMock(AttributeTypeValidator::class);
        $manager = new AttributeDefinitionManager($repository, $validator);

        $attribute = new SubscriberAttributeDefinition();
        $attribute->setName('Old');

        $dto = new AttributeDefinitionDto(
            name: 'New',
            type: 'text',
            listOrder: 5,
            defaultValue: 'Canada',
            required: false,
            tableName: 'custom_attrs'
        );

        $repository->expects($this->once())
            ->method('findOneByName')
            ->with('New')
            ->willReturn(null);

        $repository->expects($this->once())->method('save')->with($attribute);

        $updated = $manager->update($attribute, $dto);

        $this->assertSame('New', $updated->getName());
        $this->assertSame('text', $updated->getType());
        $this->assertSame(5, $updated->getListOrder());
        $this->assertSame('Canada', $updated->getDefaultValue());
        $this->assertFalse($updated->isRequired());
        $this->assertSame('custom_attrs', $updated->getTableName());
    }

    public function testUpdateThrowsWhenAnotherAttributeWithSameNameExists(): void
    {
        $repository = $this->createMock(SubscriberAttributeDefinitionRepository::class);
        $validator = $this->createMock(AttributeTypeValidator::class);
        $manager = new AttributeDefinitionManager($repository, $validator);

        $dto = new AttributeDefinitionDto(
            name: 'Existing',
            type: 'text',
            listOrder: 5,
            defaultValue: 'Canada',
            required: false,
            tableName: 'custom_attrs'
        );

        $current = new SubscriberAttributeDefinition();
        $current->setName('Old');

        $other = $this->createMock(SubscriberAttributeDefinition::class);
        $other->method('getId')->willReturn(999);

        $repository->expects($this->once())
            ->method('findOneByName')
            ->with('Existing')
            ->willReturn($other);

        $this->expectException(AttributeDefinitionCreationException::class);

        $manager->update($current, $dto);
    }

    public function testDeleteAttributeDefinition(): void
    {
        $repository = $this->createMock(SubscriberAttributeDefinitionRepository::class);
        $validator = $this->createMock(AttributeTypeValidator::class);
        $manager = new AttributeDefinitionManager($repository, $validator);

        $attribute = new SubscriberAttributeDefinition();

        $repository->expects($this->once())->method('remove')->with($attribute);

        $manager->delete($attribute);

        $this->assertTrue(true);
    }
}
