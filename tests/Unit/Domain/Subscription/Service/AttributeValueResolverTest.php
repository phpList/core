<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service;

use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeValue;
use PhpList\Core\Domain\Subscription\Service\Provider\AttributeValueProvider;
use PhpList\Core\Domain\Subscription\Service\Resolver\AttributeValueResolver;
use PHPUnit\Framework\TestCase;

final class AttributeValueResolverTest extends TestCase
{
    public function testResolveReturnsEmptyWhenNoProviderSupports(): void
    {
        $def = $this->createMock(SubscriberAttributeDefinition::class);
        $userAttr = $this->createMock(SubscriberAttributeValue::class);
        $userAttr->method('getAttributeDefinition')->willReturn($def);

        $p1 = $this->createMock(AttributeValueProvider::class);
        $p1->expects($this->once())->method('supports')->with($def)->willReturn(false);
        $p1->expects($this->never())->method('getValue');

        $p2 = $this->createMock(AttributeValueProvider::class);
        $p2->expects($this->once())->method('supports')->with($def)->willReturn(false);
        $p2->expects($this->never())->method('getValue');

        $resolver = new AttributeValueResolver([$p1, $p2]);

        self::assertSame('', $resolver->resolve($userAttr));
    }

    public function testResolveReturnsValueFromFirstSupportingProvider(): void
    {
        $def = $this->createMock(SubscriberAttributeDefinition::class);
        $userAttr = $this->createMock(SubscriberAttributeValue::class);
        $userAttr->method('getAttributeDefinition')->willReturn($def);

        $nonSupporting = $this->createMock(AttributeValueProvider::class);
        $nonSupporting->expects($this->once())->method('supports')->with($def)->willReturn(false);
        $nonSupporting->expects($this->never())->method('getValue');

        $supporting = $this->createMock(AttributeValueProvider::class);
        $supporting->expects($this->once())->method('supports')->with($def)->willReturn(true);
        $supporting->expects($this->once())
            ->method('getValue')
            ->with($def, $userAttr)
            ->willReturn('Resolved Value');

        // This provider should never be interrogated because resolver exits early.
        $afterFirstMatch = $this->createMock(AttributeValueProvider::class);
        $afterFirstMatch->expects($this->never())->method('supports');
        $afterFirstMatch->expects($this->never())->method('getValue');

        $resolver = new AttributeValueResolver([$nonSupporting, $supporting, $afterFirstMatch]);

        self::assertSame('Resolved Value', $resolver->resolve($userAttr));
    }

    public function testResolveHonorsProviderOrderFirstMatchWins(): void
    {
        $def = $this->createMock(SubscriberAttributeDefinition::class);
        $userAttr = $this->createMock(SubscriberAttributeValue::class);
        $userAttr->method('getAttributeDefinition')->willReturn($def);

        $firstSupporting = $this->createMock(AttributeValueProvider::class);
        $firstSupporting->expects($this->once())->method('supports')->with($def)->willReturn(true);
        $firstSupporting->expects($this->once())
            ->method('getValue')
            ->with($def, $userAttr)
            ->willReturn('first');

        $secondSupporting = $this->createMock(AttributeValueProvider::class);
        // Must not be called because the first already matched
        $secondSupporting->expects($this->never())->method('supports');
        $secondSupporting->expects($this->never())->method('getValue');

        $resolver = new AttributeValueResolver([$firstSupporting, $secondSupporting]);

        self::assertSame('first', $resolver->resolve($userAttr));
    }
}
