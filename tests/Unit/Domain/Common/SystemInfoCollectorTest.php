<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Common;

use PhpList\Core\Domain\Common\SystemInfoCollector;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class SystemInfoCollectorTest extends TestCase
{
    private RequestStack&MockObject $requestStack;

    protected function setUp(): void
    {
        $this->requestStack = $this->createMock(RequestStack::class);
    }

    public function testCollectReturnsSanitizedPairsWithDefaults(): void
    {
        $server = [
            'HTTP_USER_AGENT' => 'Agent <b>X</b>"',
            'HTTP_REFERER' => 'https://example.com/?q=<script>alert(1)</script>',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.5, 203.0.113.7',
            'REQUEST_URI' => '/path?x=1&y="z"<w>',
            'REMOTE_ADDR' => '203.0.113.10',
        ];
        $request = new Request(query: [], request: [], attributes: [], cookies: [], files: [], server: $server);

        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $collector = new SystemInfoCollector($this->requestStack);
        $result = $collector->collect();

        $expected = [
            'HTTP_USER_AGENT' => 'Agent &lt;b&gt;X&lt;/b&gt;&quot;',
            'HTTP_REFERER' => 'https://example.com/?q=&lt;script&gt;alert(1)&lt;/script&gt;',
            'REMOTE_ADDR' => '203.0.113.10',
            'REQUEST_URI' => '/path?x=1&amp;y=&quot;z&quot;&lt;w&gt;',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.5, 203.0.113.7',
        ];

        $this->assertSame($expected, $result);
    }

    public function testCollectUsesConfiguredKeysAndSkipsMissing(): void
    {
        $server = [
            'HTTP_USER_AGENT' => 'UA',
            'REQUEST_URI' => '/only/uri',
            'REMOTE_ADDR' => '198.51.100.10',
        ];
        $request = new Request(query: [], request: [], attributes: [], cookies: [], files: [], server: $server);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $collector = new SystemInfoCollector($this->requestStack, ['REQUEST_URI', 'UNKNOWN', 'REMOTE_ADDR']);
        $result = $collector->collect();

        $expected = [
            'REQUEST_URI' => '/only/uri',
            'REMOTE_ADDR' => '198.51.100.10',
        ];

        $this->assertSame($expected, $result);
    }

    public function testCollectAsStringFormatsLinesWithLeadingNewline(): void
    {
        $server = [
            'HTTP_USER_AGENT' => 'UA',
            'HTTP_REFERER' => 'https://ref.example',
            'REMOTE_ADDR' => '192.0.2.5',
            'REQUEST_URI' => '/abc',
            'HTTP_X_FORWARDED_FOR' => '1.1.1.1',
        ];
        $request = new Request(query: [], request: [], attributes: [], cookies: [], files: [], server: $server);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $collector = new SystemInfoCollector($this->requestStack);
        $string = $collector->collectAsString();

        $expected = "\n" . implode("\n", [
            'HTTP_USER_AGENT = UA',
            'HTTP_REFERER = https://ref.example',
            'REMOTE_ADDR = 192.0.2.5',
            'REQUEST_URI = /abc',
            'HTTP_X_FORWARDED_FOR = 1.1.1.1',
        ]);

        $this->assertSame($expected, $string);
    }
}
