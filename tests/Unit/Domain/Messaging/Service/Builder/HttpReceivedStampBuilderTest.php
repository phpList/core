<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Builder;

use PhpList\Core\Domain\Messaging\Service\Builder\HttpReceivedStampBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class HttpReceivedStampBuilderTest extends TestCase
{
    public function testReturnsNullWhenNoRequest(): void
    {
        $stack = new RequestStack();
        $builder = new HttpReceivedStampBuilder($stack, 'api.example.test');

        self::assertNull($builder->buildStamp());
    }

    public function testReturnsNullWhenNoClientIp(): void
    {
        $stack = new RequestStack();
        $request = new Request();
        // Do not set REMOTE_ADDR to simulate missing client IP
        $stack->push($request);

        $builder = new HttpReceivedStampBuilder($stack, 'api.example.test');

        self::assertNull($builder->buildStamp());
    }

    public function testBuildsStampWithRemoteHostAndFixedTime(): void
    {
        $stack = new RequestStack();
        $request = new Request();
        // Set client IP and remote host explicitly
        $request->server->set('REMOTE_ADDR', '203.0.113.5');
        $request->server->set('REMOTE_HOST', 'client.example.org');
        // Fix the request time for deterministic output (Unix epoch start)
        $request->server->set('REQUEST_TIME', 0);
        $stack->push($request);

        $builder = new HttpReceivedStampBuilder($stack, 'api.example.test');

        $stamp = $builder->buildStamp();

        self::assertSame(
            'from client.example.org [203.0.113.5] by api.example.test with HTTP; Thu, 01 Jan 1970 00:00:00 +0000',
            $stamp
        );
    }

    public function testBuildsStampWithIpOnlyNoReverseDns(): void
    {
        $stack = new RequestStack();
        $request = new Request();
        // Use a TEST-NET IP which should not resolve via gethostbyaddr
        $request->server->set('REMOTE_ADDR', '203.0.113.55');
        // Ensure no REMOTE_HOST so builder attempts reverse DNS, which should fail and fallback to IP only
        $request->server->remove('REMOTE_HOST');
        $request->server->set('REQUEST_TIME', 0);
        $stack->push($request);

        $builder = new HttpReceivedStampBuilder($stack, 'api.example.test');

        $stamp = $builder->buildStamp();

        self::assertSame(
            'from [203.0.113.55] by api.example.test with HTTP; Thu, 01 Jan 1970 00:00:00 +0000',
            $stamp
        );
    }
}
