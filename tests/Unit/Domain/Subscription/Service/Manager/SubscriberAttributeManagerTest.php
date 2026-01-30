<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service\Manager;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Subscription\Exception\SubscriberAttributeCreationException;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeValue;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeDefinitionRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeValueRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\DynamicListAttrManager;
use PhpList\Core\Domain\Subscription\Service\Manager\DynamicListAttrTablesManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberAttributeManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Translator;

class SubscriberAttributeManagerTest extends TestCase
{
    public function testCreateNewSubscriberAttribute(): void
    {
        $subscriber = new Subscriber();
        $definition = new SubscriberAttributeDefinition();

        $subscriberAttrRepo = $this->createMock(SubscriberAttributeValueRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $subscriberAttrRepo->expects(self::once())
            ->method('findOneBySubscriberAndAttribute')
            ->with($subscriber, $definition)
            ->willReturn(null);

        $entityManager->expects(self::once())
            ->method('persist')
            ->with(self::callback(function (SubscriberAttributeValue $attr) {
                return $attr->getValue() === 'US';
            }));

        $manager = new SubscriberAttributeManager(
            attributeRepository: $subscriberAttrRepo,
            attrDefinitionRepository: $this->createMock(SubscriberAttributeDefinitionRepository::class),
            entityManager: $entityManager,
            translator: new Translator('en'),
        );
        $attribute = $manager->createOrUpdate($subscriber, $definition, 'US');

        self::assertInstanceOf(SubscriberAttributeValue::class, $attribute);
        self::assertSame('US', $attribute->getValue());
    }

    public function testUpdateExistingSubscriberAttribute(): void
    {
        $subscriber = new Subscriber();
        $definition = new SubscriberAttributeDefinition();
        $existing = new SubscriberAttributeValue($definition, $subscriber);
        $existing->setValue('Old');

        $subscriberAttrRepo = $this->createMock(SubscriberAttributeValueRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $subscriberAttrRepo->expects(self::once())
            ->method('findOneBySubscriberAndAttribute')
            ->with($subscriber, $definition)
            ->willReturn($existing);

        $entityManager->expects(self::once())
            ->method('persist')
            ->with($existing);

        $manager = new SubscriberAttributeManager(
            attributeRepository: $subscriberAttrRepo,
            attrDefinitionRepository: $this->createMock(SubscriberAttributeDefinitionRepository::class),
            entityManager: $entityManager,
            translator: new Translator('en'),
        );
        $result = $manager->createOrUpdate($subscriber, $definition, 'Updated');

        self::assertSame('Updated', $result->getValue());
    }

    public function testCreateFailsWhenValueAndDefaultAreNull(): void
    {
        $subscriber = new Subscriber();
        $definition = new SubscriberAttributeDefinition();

        $subscriberAttrRepo = $this->createMock(SubscriberAttributeValueRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $subscriberAttrRepo->method('findOneBySubscriberAndAttribute')->willReturn(null);

        $manager = new SubscriberAttributeManager(
            attributeRepository: $subscriberAttrRepo,
            attrDefinitionRepository: $this->createMock(SubscriberAttributeDefinitionRepository::class),
            entityManager: $entityManager,
            translator: new Translator('en'),
        );

        $this->expectException(SubscriberAttributeCreationException::class);
        $this->expectExceptionMessage('Value is required');

        $manager->createOrUpdate($subscriber, $definition, null);
    }

    public function testGetSubscriberAttribute(): void
    {
        $subscriberAttrRepo = $this->createMock(SubscriberAttributeValueRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $expected = new SubscriberAttributeValue(new SubscriberAttributeDefinition(), new Subscriber());
        $subscriberAttrRepo->expects(self::once())
            ->method('findOneBySubscriberIdAndAttributeId')
            ->with(5, 10)
            ->willReturn($expected);

        $manager = new SubscriberAttributeManager(
            attributeRepository: $subscriberAttrRepo,
            attrDefinitionRepository: $this->createMock(SubscriberAttributeDefinitionRepository::class),
            entityManager: $entityManager,
            translator: new Translator('en'),
        );
        $result = $manager->getSubscriberAttribute(5, 10);

        self::assertSame($expected, $result);
    }

    public function testDeleteSubscriberAttribute(): void
    {
        $subscriberAttrRepo = $this->createMock(SubscriberAttributeValueRepository::class);
        $attribute = $this->createMock(SubscriberAttributeValue::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $subscriberAttrRepo->expects(self::once())
            ->method('remove')
            ->with($attribute);

        $manager = new SubscriberAttributeManager(
            attributeRepository: $subscriberAttrRepo,
            attrDefinitionRepository: $this->createMock(SubscriberAttributeDefinitionRepository::class),
            entityManager: $entityManager,
            translator: new Translator('en'),
        );
        $manager->delete($attribute);

        self::assertTrue(true);
    }
}
