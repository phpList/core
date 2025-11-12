<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service\Manager;

use PhpList\Core\Domain\Subscription\Exception\AttributeDefinitionCreationException;
use PhpList\Core\Domain\Subscription\Model\AttributeTypeEnum;
use PhpList\Core\Domain\Subscription\Model\Dto\AttributeDefinitionDto;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeDefinitionRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\AttributeDefinitionManager;
use PhpList\Core\Domain\Subscription\Service\Manager\DynamicListAttrManager;
use PhpList\Core\Domain\Subscription\Service\Manager\DynamicListAttrTablesManager;
use PhpList\Core\Domain\Subscription\Validator\AttributeTypeValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Translation\Translator;

class AttributeDefinitionManagerTest extends TestCase
{
    public function testCreateAttributeDefinition(): void
    {
        $repository = $this->createMock(SubscriberAttributeDefinitionRepository::class);
        $validator = $this->createMock(AttributeTypeValidator::class);
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function ($message) {
            return new Envelope($message);
        });
        $dynamicTablesManager = new DynamicListAttrTablesManager(
            definitionRepository: $this->createMock(SubscriberAttributeDefinitionRepository::class),
            messageBus: $bus,
        );
        $manager = new AttributeDefinitionManager(
            definitionRepository: $repository,
            attributeTypeValidator: $validator,
            translator: new Translator('en'),
            dynamicListAttrManager: $this->createMock(DynamicListAttrManager::class),
            dynamicTablesManager: $dynamicTablesManager,
        );

        $dto = new AttributeDefinitionDto(
            name: 'Country',
            type: AttributeTypeEnum::Checkbox,
            listOrder: 1,
            defaultValue: 'US',
            required: true,
        );

        $repository->expects($this->once())
            ->method('findOneByName')
            ->with('Country')
            ->willReturn(null);

        $repository->expects($this->once())->method('persist');

        $attribute = $manager->create($dto);

        $this->assertInstanceOf(SubscriberAttributeDefinition::class, $attribute);
        $this->assertSame('Country', $attribute->getName());
        $this->assertSame(AttributeTypeEnum::Checkbox, $attribute->getType());
        $this->assertSame(1, $attribute->getListOrder());
        $this->assertSame('US', $attribute->getDefaultValue());
        $this->assertTrue($attribute->isRequired());
        $this->assertSame('country', $attribute->getTableName());
    }

    public function testCreateThrowsWhenAttributeAlreadyExists(): void
    {
        $repository = $this->createMock(SubscriberAttributeDefinitionRepository::class);
        $validator = $this->createMock(AttributeTypeValidator::class);
        $manager = new AttributeDefinitionManager(
            definitionRepository: $repository,
            attributeTypeValidator: $validator,
            translator: new Translator('en'),
            dynamicListAttrManager: $this->createMock(DynamicListAttrManager::class),
            dynamicTablesManager: $this->createMock(DynamicListAttrTablesManager::class),
        );

        $dto = new AttributeDefinitionDto(
            name: 'Country',
            type: AttributeTypeEnum::Checkbox,
            listOrder: 1,
            defaultValue: 'US',
            required: true,
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
        $manager = new AttributeDefinitionManager(
            definitionRepository: $repository,
            attributeTypeValidator: $validator,
            translator: new Translator('en'),
            dynamicListAttrManager: $this->createMock(DynamicListAttrManager::class),
            dynamicTablesManager: $this->createMock(DynamicListAttrTablesManager::class),
        );

        $attribute = new SubscriberAttributeDefinition();
        $attribute->setName('Old');

        $dto = new AttributeDefinitionDto(
            name: 'New',
            type: AttributeTypeEnum::Text,
            listOrder: 5,
            defaultValue: 'Canada',
            required: false,
        );

        $repository->expects($this->once())
            ->method('findOneByName')
            ->with('New')
            ->willReturn(null);

        $updated = $manager->update(attributeDefinition: $attribute, attributeDefinitionDto: $dto);

        $this->assertSame('New', $updated->getName());
        $this->assertSame(AttributeTypeEnum::Text, $updated->getType());
        $this->assertSame(5, $updated->getListOrder());
        $this->assertSame('Canada', $updated->getDefaultValue());
        $this->assertFalse($updated->isRequired());
        $this->assertSame(null, $updated->getTableName());
    }

    public function testUpdateThrowsWhenAnotherAttributeWithSameNameExists(): void
    {
        $repository = $this->createMock(SubscriberAttributeDefinitionRepository::class);
        $validator = $this->createMock(AttributeTypeValidator::class);
        $manager = new AttributeDefinitionManager(
            definitionRepository: $repository,
            attributeTypeValidator: $validator,
            translator: new Translator('en'),
            dynamicListAttrManager: $this->createMock(DynamicListAttrManager::class),
            dynamicTablesManager: $this->createMock(DynamicListAttrTablesManager::class),
        );

        $dto = new AttributeDefinitionDto(
            name: 'Existing',
            type: AttributeTypeEnum::Text,
            listOrder: 5,
            defaultValue: 'Canada',
            required: false,
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

        $manager->update(attributeDefinition: $current, attributeDefinitionDto: $dto);
    }

    public function testDeleteAttributeDefinition(): void
    {
        $repository = $this->createMock(SubscriberAttributeDefinitionRepository::class);
        $validator = $this->createMock(AttributeTypeValidator::class);
        $manager = new AttributeDefinitionManager(
            definitionRepository: $repository,
            attributeTypeValidator: $validator,
            translator: new Translator('en'),
            dynamicListAttrManager: $this->createMock(DynamicListAttrManager::class),
            dynamicTablesManager: $this->createMock(DynamicListAttrTablesManager::class),
        );

        $attribute = new SubscriberAttributeDefinition();

        $repository->expects($this->once())->method('remove')->with($attribute);

        $manager->delete($attribute);

        $this->assertTrue(true);
    }
}
