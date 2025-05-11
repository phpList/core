<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Service\Manager;

use PhpList\Core\Domain\Exception\SubscriberAttributeCreationException;
use PhpList\Core\Domain\Model\Subscription\SubscriberAttributeDefinition;
use PhpList\Core\Domain\Model\Subscription\Dto\SubscriberAttributeDto;
use PhpList\Core\Domain\Model\Subscription\Subscriber;
use PhpList\Core\Domain\Model\Subscription\SubscriberAttributeValue;
use PhpList\Core\Domain\Repository\Subscription\SubscriberAttributeDefinitionRepository;
use PhpList\Core\Domain\Repository\Subscription\SubscriberAttributeValueRepository;
use PhpList\Core\Domain\Repository\Subscription\SubscriberRepository;
use PhpList\Core\Domain\Service\Manager\SubscriberAttributeManager;
use PHPUnit\Framework\TestCase;

class SubscriberAttributeManagerTest extends TestCase
{
    public function testCreateNewSubscriberAttribute(): void
    {
        $subscriber = new Subscriber();
        $definition = new SubscriberAttributeDefinition();

        $dto = new SubscriberAttributeDto(
            subscriberId: 1,
            attributeDefinitionId: 2,
            value: 'US'
        );

        $subscriberRepo = $this->createMock(SubscriberRepository::class);
        $attributeDefRepo = $this->createMock(SubscriberAttributeDefinitionRepository::class);
        $subscriberAttrRepo = $this->createMock(SubscriberAttributeValueRepository::class);

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
            ->with(self::callback(function (SubscriberAttributeValue $attr) {
                return $attr->getValue() === 'US';
            }));

        $manager = new SubscriberAttributeManager($attributeDefRepo, $subscriberAttrRepo, $subscriberRepo);
        $attribute = $manager->createOrUpdate($dto);

        self::assertInstanceOf(SubscriberAttributeValue::class, $attribute);
        self::assertSame('US', $attribute->getValue());
    }

    public function testUpdateExistingSubscriberAttribute(): void
    {
        $subscriber = new Subscriber();
        $definition = new SubscriberAttributeDefinition();

        $existing = new SubscriberAttributeValue($definition, $subscriber);
        $existing->setValue('Old');

        $dto = new SubscriberAttributeDto(1, 2, 'Updated');

        $subscriberRepo = $this->createMock(SubscriberRepository::class);
        $attributeDefRepo = $this->createMock(SubscriberAttributeDefinitionRepository::class);
        $subscriberAttrRepo = $this->createMock(SubscriberAttributeValueRepository::class);

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
        $attributeDefRepo = $this->createMock(SubscriberAttributeDefinitionRepository::class);
        $subscriberAttrRepo = $this->createMock(SubscriberAttributeValueRepository::class);

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
        $attributeDefRepo = $this->createMock(SubscriberAttributeDefinitionRepository::class);
        $subscriberAttrRepo = $this->createMock(SubscriberAttributeValueRepository::class);

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
        $expected = new SubscriberAttributeValue(new SubscriberAttributeDefinition(), new Subscriber());

        $subscriberRepo = $this->createMock(SubscriberRepository::class);
        $attributeDefRepo = $this->createMock(SubscriberAttributeDefinitionRepository::class);
        $subscriberAttrRepo = $this->createMock(SubscriberAttributeValueRepository::class);

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
        $attributeDefRepo = $this->createMock(SubscriberAttributeDefinitionRepository::class);
        $subscriberAttrRepo = $this->createMock(SubscriberAttributeValueRepository::class);

        $attribute = $this->createMock(SubscriberAttributeValue::class);

        $attributeDefRepo->expects(self::once())->method('remove')->with($attribute);

        $manager = new SubscriberAttributeManager($attributeDefRepo, $subscriberAttrRepo, $subscriberRepo);
        $manager->delete($attribute);

        self::assertTrue(true);
    }
}
