<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Service\Manager;

use PhpList\Core\Domain\Exception\SubscriberAttributeCreationException;
use PhpList\Core\Domain\Model\Subscription\AttributeDefinition;
use PhpList\Core\Domain\Model\Subscription\Dto\SubscriberAttributeDto;
use PhpList\Core\Domain\Model\Subscription\Subscriber;
use PhpList\Core\Domain\Model\Subscription\SubscriberAttribute;
use PhpList\Core\Domain\Repository\Subscription\AttributeDefinitionRepository;
use PhpList\Core\Domain\Repository\Subscription\SubscriberAttributeRepository;
use PhpList\Core\Domain\Repository\Subscription\SubscriberRepository;
use PhpList\Core\Domain\Service\Manager\SubscriberAttributeManager;
use PHPUnit\Framework\TestCase;

class SubscriberAttributeManagerTest extends TestCase
{
    public function testCreateNewSubscriberAttribute(): void
    {
        $subscriber = new Subscriber();
        $definition = new AttributeDefinition();

        $dto = new SubscriberAttributeDto(
            subscriberId: 1,
            attributeDefinitionId: 2,
            value: 'US'
        );

        $subscriberRepo = $this->createMock(SubscriberRepository::class);
        $attributeDefRepo = $this->createMock(AttributeDefinitionRepository::class);
        $subscriberAttrRepo = $this->createMock(SubscriberAttributeRepository::class);

        $subscriberRepo->expects(self::once())
            ->method('find')
            ->with(1)
            ->willReturn($subscriber);

        $attributeDefRepo->expects(self::once())
            ->method('find')
            ->with(2)
            ->willReturn($definition);

        $subscriberAttrRepo->expects(self::once())
            ->method('findOneBySubscriberAndAttribute')
            ->with($subscriber, $definition)
            ->willReturn(null);

        $subscriberAttrRepo->expects(self::once())
            ->method('save')
            ->with(self::callback(function (SubscriberAttribute $attr) {
                return $attr->getValue() === 'US';
            }));

        $manager = new SubscriberAttributeManager($attributeDefRepo, $subscriberAttrRepo, $subscriberRepo);
        $attribute = $manager->createOrUpdate($dto);

        self::assertInstanceOf(SubscriberAttribute::class, $attribute);
        self::assertSame('US', $attribute->getValue());
    }

    public function testUpdateExistingSubscriberAttribute(): void
    {
        $subscriber = new Subscriber();
        $definition = new AttributeDefinition();

        $existing = new SubscriberAttribute($definition, $subscriber);
        $existing->setValue('Old');

        $dto = new SubscriberAttributeDto(1, 2, 'Updated');

        $subscriberRepo = $this->createMock(SubscriberRepository::class);
        $attributeDefRepo = $this->createMock(AttributeDefinitionRepository::class);
        $subscriberAttrRepo = $this->createMock(SubscriberAttributeRepository::class);

        $subscriberRepo->method('find')->willReturn($subscriber);
        $attributeDefRepo->method('find')->willReturn($definition);

        $subscriberAttrRepo->expects(self::once())
            ->method('findOneBySubscriberAndAttribute')
            ->with($subscriber, $definition)
            ->willReturn($existing);

        $subscriberAttrRepo->expects(self::once())->method('save')->with($existing);

        $manager = new SubscriberAttributeManager($attributeDefRepo, $subscriberAttrRepo, $subscriberRepo);
        $result = $manager->createOrUpdate($dto);

        self::assertSame('Updated', $result->getValue());
    }

    public function testCreateFailsIfSubscriberNotFound(): void
    {
        $subscriberRepo = $this->createMock(SubscriberRepository::class);
        $attributeDefRepo = $this->createMock(AttributeDefinitionRepository::class);
        $subscriberAttrRepo = $this->createMock(SubscriberAttributeRepository::class);

        $subscriberRepo->method('find')->willReturn(null);

        $dto = new SubscriberAttributeDto(1, 2, 'US');

        $manager = new SubscriberAttributeManager($attributeDefRepo, $subscriberAttrRepo, $subscriberRepo);

        $this->expectException(SubscriberAttributeCreationException::class);
        $this->expectExceptionMessage('Subscriber does not exist');

        $manager->createOrUpdate($dto);
    }

    public function testCreateFailsIfAttributeDefinitionNotFound(): void
    {
        $subscriber = new Subscriber();

        $subscriberRepo = $this->createMock(SubscriberRepository::class);
        $attributeDefRepo = $this->createMock(AttributeDefinitionRepository::class);
        $subscriberAttrRepo = $this->createMock(SubscriberAttributeRepository::class);

        $subscriberRepo->method('find')->willReturn($subscriber);
        $attributeDefRepo->method('find')->willReturn(null);

        $dto = new SubscriberAttributeDto(1, 2, 'US');

        $manager = new SubscriberAttributeManager($attributeDefRepo, $subscriberAttrRepo, $subscriberRepo);

        $this->expectException(SubscriberAttributeCreationException::class);
        $this->expectExceptionMessage('Attribute definition does not exist');

        $manager->createOrUpdate($dto);
    }

    public function testGetSubscriberAttribute(): void
    {
        $expected = new SubscriberAttribute(new AttributeDefinition(), new Subscriber());

        $subscriberRepo = $this->createMock(SubscriberRepository::class);
        $attributeDefRepo = $this->createMock(AttributeDefinitionRepository::class);
        $subscriberAttrRepo = $this->createMock(SubscriberAttributeRepository::class);

        $subscriberAttrRepo->expects(self::once())
            ->method('findOneBySubscriberIdAndAttributeId')
            ->with(5, 10)
            ->willReturn($expected);

        $manager = new SubscriberAttributeManager($attributeDefRepo, $subscriberAttrRepo, $subscriberRepo);

        $result = $manager->getSubscriberAttribute(5, 10);

        self::assertSame($expected, $result);
    }

    public function testDeleteSubscriberAttribute(): void
    {
        $subscriberRepo = $this->createMock(SubscriberRepository::class);
        $attributeDefRepo = $this->createMock(AttributeDefinitionRepository::class);
        $subscriberAttrRepo = $this->createMock(SubscriberAttributeRepository::class);

        $attribute = $this->createMock(SubscriberAttribute::class);

        $attributeDefRepo->expects(self::once())->method('remove')->with($attribute);

        $manager = new SubscriberAttributeManager($attributeDefRepo, $subscriberAttrRepo, $subscriberRepo);
        $manager->delete($attribute);

        self::assertTrue(true);
    }
}
