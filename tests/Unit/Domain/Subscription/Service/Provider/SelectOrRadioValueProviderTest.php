<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service\Provider;

use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeValue;
use PhpList\Core\Domain\Subscription\Repository\DynamicListAttrRepository;
use PhpList\Core\Domain\Subscription\Service\Provider\SelectOrRadioValueProvider;
use PHPUnit\Framework\TestCase;

final class SelectOrRadioValueProviderTest extends TestCase
{
    public function testSupportsReturnsTrueForSelectAndRadio(): void
    {
        $repo = $this->createMock(DynamicListAttrRepository::class);
        $provider = new SelectOrRadioValueProvider($repo);

        $attrSelect = $this->createMock(SubscriberAttributeDefinition::class);
        $attrSelect->method('getType')->willReturn('select');
        self::assertTrue($provider->supports($attrSelect));

        $attrRadio = $this->createMock(SubscriberAttributeDefinition::class);
        $attrRadio->method('getType')->willReturn('radio');
        self::assertTrue($provider->supports($attrRadio));
    }

    public function testSupportsReturnsFalseForOtherTypes(): void
    {
        $repo = $this->createMock(DynamicListAttrRepository::class);
        $provider = new SelectOrRadioValueProvider($repo);

        $attr = $this->createMock(SubscriberAttributeDefinition::class);
        $attr->method('getType')->willReturn('checkboxgroup');

        self::assertFalse($provider->supports($attr));
    }

    public function testGetValueReturnsEmptyWhenNoTableName(): void
    {
        $repo = $this->createMock(DynamicListAttrRepository::class);
        $provider = new SelectOrRadioValueProvider($repo);

        $attr = $this->createMock(SubscriberAttributeDefinition::class);
        $attr->method('getTableName')->willReturn(null);

        $val = $this->createMock(SubscriberAttributeValue::class);
        $val->method('getValue')->willReturn('10');

        $repo->expects($this->never())->method('fetchSingleOptionName');

        self::assertSame('', $provider->getValue($attr, $val));
    }

    public function testGetValueReturnsEmptyWhenValueNullOrNonPositive(): void
    {
        $repo = $this->createMock(DynamicListAttrRepository::class);
        $provider = new SelectOrRadioValueProvider($repo);

        $attr = $this->createMock(SubscriberAttributeDefinition::class);
        $attr->method('getTableName')->willReturn('products');

        $valNull = $this->createMock(SubscriberAttributeValue::class);
        $valNull->method('getValue')->willReturn(null);
        $repo->expects($this->never())->method('fetchSingleOptionName');
        self::assertSame('', $provider->getValue($attr, $valNull));

        $valZero = $this->createMock(SubscriberAttributeValue::class);
        $valZero->method('getValue')->willReturn('0');
        self::assertSame('', $provider->getValue($attr, $valZero));

        $valNegative = $this->createMock(SubscriberAttributeValue::class);
        $valNegative->method('getValue')->willReturn('-5');
        self::assertSame('', $provider->getValue($attr, $valNegative));
    }

    public function testGetValueReturnsEmptyWhenRepoReturnsNull(): void
    {
        $repo = $this->createMock(DynamicListAttrRepository::class);
        $provider = new SelectOrRadioValueProvider($repo);

        $attr = $this->createMock(SubscriberAttributeDefinition::class);
        $attr->method('getTableName')->willReturn('users');

        $val = $this->createMock(SubscriberAttributeValue::class);
        $val->method('getValue')->willReturn('7');

        $repo->expects($this->once())
            ->method('fetchSingleOptionName')
            ->with('users', 7)
            ->willReturn(null);

        self::assertSame('', $provider->getValue($attr, $val));
    }

    public function testGetValueHappyPathReturnsNameFromRepo(): void
    {
        $repo = $this->createMock(DynamicListAttrRepository::class);
        $provider = new SelectOrRadioValueProvider($repo);

        $attr = $this->createMock(SubscriberAttributeDefinition::class);
        $attr->method('getTableName')->willReturn('countries');

        $val = $this->createMock(SubscriberAttributeValue::class);
        $val->method('getValue')->willReturn('  42 ');

        $repo->expects($this->once())
            ->method('fetchSingleOptionName')
            ->with('countries', 42)
            ->willReturn('Armenia');

        self::assertSame('Armenia', $provider->getValue($attr, $val));
    }
}
