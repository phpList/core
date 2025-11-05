<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Bounce\Service;

use PhpList\Core\Bounce\Service\BounceActionResolver;
use PhpList\Core\Bounce\Service\Handler\BounceActionHandlerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class BounceActionResolverTest extends TestCase
{
    private BounceActionHandlerInterface&MockObject $fooHandler;
    private BounceActionHandlerInterface&MockObject $barHandler;
    private BounceActionResolver $resolver;

    protected function setUp(): void
    {
        $this->fooHandler = $this->createMock(BounceActionHandlerInterface::class);
        $this->barHandler = $this->createMock(BounceActionHandlerInterface::class);
        $this->fooHandler->method('supports')->willReturnCallback(fn ($action) => $action === 'foo');
        $this->barHandler->method('supports')->willReturnCallback(fn ($action) => $action === 'bar');

        $this->resolver = new BounceActionResolver(
            [
                $this->fooHandler,
                $this->barHandler,
            ]
        );
    }

    public function testHasReturnsTrueWhenHandlerSupportsAction(): void
    {
        $this->assertTrue($this->resolver->has('foo'));
        $this->assertTrue($this->resolver->has('bar'));
        $this->assertFalse($this->resolver->has('baz'));
    }

    public function testResolveReturnsSameInstanceAndCaches(): void
    {
        $first = $this->resolver->resolve('foo');
        $second = $this->resolver->resolve('foo');

        $this->assertSame($first, $second);

        $this->assertInstanceOf(BounceActionHandlerInterface::class, $first);
    }

    public function testResolveThrowsWhenNoHandlerFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No handler found for action "baz".');

        $this->resolver->resolve('baz');
    }

    public function testHandleDelegatesToResolvedHandler(): void
    {
        $context = ['key' => 'value', 'n' => 42];
        $this->fooHandler->expects($this->once())->method('handle');
        $this->barHandler->expects($this->never())->method('handle');
        $this->resolver->handle('foo', $context);
    }
}
