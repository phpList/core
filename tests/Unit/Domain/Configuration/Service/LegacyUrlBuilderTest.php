<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service;

use PhpList\Core\Domain\Configuration\Service\LegacyUrlBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \PhpList\Core\Domain\Configuration\Service\LegacyUrlBuilder
 */
final class LegacyUrlBuilderTest extends TestCase
{
    /**
     * @dataProvider provideWithUidCases
     */
    public function testWithUid(string $baseUrl, string $uid, string $expected): void
    {
        $builder = new LegacyUrlBuilder();

        $actual = $builder->withUid($baseUrl, $uid);

        $this->assertSame($expected, $actual);
    }

    public static function provideWithUidCases(): array
    {
        return [
            'no query -> add uid' => [
                'https://example.com/page',
                'ABC123',
                'https://example.com/page?uid=ABC123',
            ],
            'existing query -> append uid' => [
                'https://example.com/page?foo=bar',
                'ABC123',
                'https://example.com/page?foo=bar&uid=ABC123',
            ],
            'existing uid -> override (uid replaced)' => [
                'https://example.com/page?uid=OLD&x=1',
                'ABC123',
                'https://example.com/page?uid=ABC123&x=1',
            ],
            'port and fragment preserved' => [
                'http://example.com:8080/path?x=1#frag',
                'ABC123',
                'http://example.com:8080/path?x=1&uid=ABC123#frag',
            ],
            'relative url -> defaults to https with empty host' => [
                '/relative/path',
                'ABC123',
                // scheme defaults to https; empty host -> "https:///" + path
                'https:///relative/path?uid=ABC123',
            ],
            'no query/fragment/port/host only' => [
                'http://example.com',
                'ZZZ',
                'http://example.com?uid=ZZZ',
            ],
        ];
    }

    public function testQueryEncodingIsUrlEncoded(): void
    {
        $builder = new LegacyUrlBuilder();

        $url = 'https://example.com/path?name=John+Doe&city=New+York';
        $result = $builder->withUid($url, 'üñíčødé space');

        // Ensure it is a valid URL and uid is url-encoded inside query
        $parts = parse_url($result);
        parse_str($parts['query'] ?? '', $query);

        $this->assertSame('John Doe', $query['name']);
        $this->assertSame('New York', $query['city']);
        $this->assertSame('üñíčødé space', $query['uid']);
    }
}
