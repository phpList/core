<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Analytics\Service;

use PhpList\Core\Domain\Analytics\Service\LinkRewriter;
use PHPUnit\Framework\TestCase;

class LinkRewriterTest extends TestCase
{
    private string $backtrackLimit;

    protected function setUp(): void
    {
        $this->backtrackLimit = (string) ini_get('pcre.backtrack_limit');
    }

    protected function tearDown(): void
    {
        ini_set('pcre.backtrack_limit', $this->backtrackLimit);
    }

    public function testHtmlIsRewritten(): void
    {
        $result = (new LinkRewriter())->rewriteHtml(
            '<p><a href="https://example.com/a">link</a></p>',
            static fn (string $url): string => $url . '?x=1'
        );

        $this->assertSame('<p><a href="https://example.com/a?x=1">link</a></p>', $result);
    }

    public function testHtmlIsKeptWhenThePatternFailsWithAPcreError(): void
    {
        $html = '<a href="https://example.com/a">' . str_repeat('word ', 200) . '</a>';
        ini_set('pcre.backtrack_limit', '10');

        $result = (new LinkRewriter())->rewriteHtml($html, static fn (string $url): string => $url . '?x=1');

        $this->assertSame($html, $result);
    }

    public function testTextIsRewritten(): void
    {
        $result = (new LinkRewriter())->rewriteText(
            'see https://example.com/a.',
            static fn (string $url): string => $url . '?x=1'
        );

        $this->assertSame('see https://example.com/a?x=1.', $result);
    }
}
