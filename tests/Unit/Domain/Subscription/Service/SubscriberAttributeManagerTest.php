<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service;

use PhpList\Core\Domain\Subscription\Exception\SubscriberAttributeCreationException;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeValue;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeValueRepository;
use PhpList\Core\Domain\Subscription\Service\SubscriberAttributeManager;
use PHPUnit\Framework\TestCase;

class SubscriberAttributeManagerTest extends TestCase
{
    public function testCreateNewSubscriberAttribute(): void
    {
        $subscriber = new Subscriber();
        $definition = new SubscriberAttributeDefinition();

        $subscriberAttrRepo = $this->createMock(SubscriberAttributeValueRepository::class);

        $subscriberAttrRepo->expects(self::once())
            ->method('findOneBySubscriberAndAttribute')
            ->with($subscriber, $definition)
            ->willReturn(null);

        $subscriberAttrRepo->expects(self::once())
            ->method('save')
            ->with(self::callback(function (SubscriberAttributeValue $attr) {
                return $attr->getValue() === 'US';
            }));

        $manager = new SubscriberAttributeManager($subscriberAttrRepo);
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
        $subscriberAttrRepo->expects(self::once())
            ->method('findOneBySubscriberAndAttribute')
            ->with($subscriber, $definition)
            ->willReturn($existing);

        $subscriberAttrRepo->expects(self::once())
            ->method('save')
            ->with($existing);

        $manager = new SubscriberAttributeManager($subscriberAttrRepo);
        $result = $manager->createOrUpdate($subscriber, $definition, 'Updated');

        self::assertSame('Updated', $result->getValue());
    }

    public function testCreateFailsWhenValueAndDefaultAreNull(): void
    {
        $subscriber = new Subscriber();
        $definition = new SubscriberAttributeDefinition();

        $subscriberAttrRepo = $this->createMock(SubscriberAttributeValueRepository::class);
        $subscriberAttrRepo->method('findOneBySubscriberAndAttribute')->willReturn(null);

        $manager = new SubscriberAttributeManager($subscriberAttrRepo);

        $this->expectException(SubscriberAttributeCreationException::class);
        $this->expectExceptionMessage('Value is required');

        $manager->createOrUpdate($subscriber, $definition, null);
    }

    public function testGetSubscriberAttribute(): void
    {
        $subscriberAttrRepo = $this->createMock(SubscriberAttributeValueRepository::class);
        $expected = new SubscriberAttributeValue(new SubscriberAttributeDefinition(), new Subscriber());

        $subscriberAttrRepo->expects(self::once())
            ->method('findOneBySubscriberIdAndAttributeId')
            ->with(5, 10)
            ->willReturn($expected);

        $manager = new SubscriberAttributeManager($subscriberAttrRepo);
        $result = $manager->getSubscriberAttribute(5, 10);

        self::assertSame($expected, $result);
    }

    public function testDeleteSubscriberAttribute(): void
    {
        $subscriberAttrRepo = $this->createMock(SubscriberAttributeValueRepository::class);
        $attribute = $this->createMock(SubscriberAttributeValue::class);

        $subscriberAttrRepo->expects(self::once())
            ->method('remove')
            ->with($attribute);

        $manager = new SubscriberAttributeManager($subscriberAttrRepo);
        $manager->delete($attribute);

        self::assertTrue(true);
    }
}
