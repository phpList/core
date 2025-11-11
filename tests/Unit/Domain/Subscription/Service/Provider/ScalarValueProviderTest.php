<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service\Provider;

use PhpList\Core\Domain\Subscription\Model\AttributeTypeEnum;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeValue;
use PhpList\Core\Domain\Subscription\Service\Provider\ScalarValueProvider;
use PHPUnit\Framework\TestCase;

final class ScalarValueProviderTest extends TestCase
{
    public function testSupportsReturnsTrueWhenTypeIsNull(): void
    {
        $provider = new ScalarValueProvider();

        $attr = $this->createMock(SubscriberAttributeDefinition::class);
        $attr->method('getType')->willReturn(null);

        self::assertTrue($provider->supports($attr));
    }

    public function testSupportsReturnsFalseWhenTypeIsNotNull(): void
    {
        $provider = new ScalarValueProvider();

        $attr = $this->createMock(SubscriberAttributeDefinition::class);
        $attr->method('getType')->willReturn(AttributeTypeEnum::Checkbox);

        self::assertFalse($provider->supports($attr));
    }

    public function testGetValueReturnsUnderlyingString(): void
    {
        $provider = new ScalarValueProvider();

        $attr = $this->createMock(SubscriberAttributeDefinition::class);

        $value = $this->createMock(SubscriberAttributeValue::class);
        $value->method('getValue')->willReturn('hello');

        self::assertSame('hello', $provider->getValue($attr, $value));
    }

    public function testGetValueReturnsEmptyStringWhenNull(): void
    {
        $provider = new ScalarValueProvider();

        $attr = $this->createMock(SubscriberAttributeDefinition::class);

        $value = $this->createMock(SubscriberAttributeValue::class);
        $value->method('getValue')->willReturn(null);

        self::assertSame('', $provider->getValue($attr, $value));
    }
}
