<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Common;

use PhpList\Core\Domain\Common\ClientIpResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ClientIpResolverTest extends TestCase
{
    private RequestStack&MockObject $requestStack;

    protected function setUp(): void
    {
        $this->requestStack = $this->createMock(RequestStack::class);
    }

    public function testResolveReturnsClientIpFromCurrentRequest(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getClientIp')->willReturn('203.0.113.10');

        $this->requestStack
            ->method('getCurrentRequest')
            ->willReturn($request);

        $resolver = new ClientIpResolver($this->requestStack);
        $this->assertSame('203.0.113.10', $resolver->resolve());
    }

    public function testResolveReturnsEmptyStringWhenClientIpIsNull(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getClientIp')->willReturn(null);

        $this->requestStack
            ->method('getCurrentRequest')
            ->willReturn($request);

        $resolver = new ClientIpResolver($this->requestStack);
        $this->assertSame('', $resolver->resolve());
    }

    public function testResolveReturnsHostAndPidWhenNoRequestAvailable(): void
    {
        $this->requestStack
            ->method('getCurrentRequest')
            ->willReturn(null);

        $resolver = new ClientIpResolver($this->requestStack);

        $expectedHost = gethostname() ?: 'localhost';
        $expected = $expectedHost . ':' . getmypid();

        $this->assertSame($expected, $resolver->resolve());
    }
}
