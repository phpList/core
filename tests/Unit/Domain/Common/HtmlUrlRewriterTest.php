<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Common;

use PhpList\Core\Domain\Common\HtmlUrlRewriter;
use PHPUnit\Framework\TestCase;

class HtmlUrlRewriterTest extends TestCase
{
    private HtmlUrlRewriter $rewriter;

    protected function setUp(): void
    {
        $this->rewriter = new HtmlUrlRewriter();
    }

    public function testAbsolutizesBasicAttributes(): void
    {
        $html = '<img src="images/pic.jpg">
            <a href="/contact">Contact</a>
            <form action="submit.php"></form>
            <table background="bg/pat.png"></table>';

        $base = 'https://example.com/base/dir/page.html';
        $out = $this->rewriter->addAbsoluteResources($html, $base);

        $this->assertStringContainsString('src="https://example.com/base/dir/images/pic.jpg"', $out);
        $this->assertStringContainsString('href="https://example.com/contact"', $out);
        $this->assertStringContainsString('action="https://example.com/base/dir/submit.php"', $out);
        $this->assertStringContainsString('background="https://example.com/base/dir/bg/pat.png"', $out);
    }

    public function testLeavesAloneSpecialSchemesAnchorsPlaceholders(): void
    {
        $html = '<a href="#section">Jump</a>
            <a href="mailto:user@example.com">Mail</a>
            <a href="javascript:void(0)">JS</a>
            <img src="data:image/png;base64,AAAA">';

        $base = 'https://example.com/base/index.html';
        $out = $this->rewriter->addAbsoluteResources($html, $base);

        $this->assertStringContainsString('href="#section"', $out);
        $this->assertStringContainsString('href="mailto:user@example.com"', $out);
        $this->assertStringContainsString('href="javascript:void(0)"', $out);
        $this->assertStringContainsString('src="data:image/png;base64,AAAA"', $out);
    }

    public function testProtocolRelativePreservesHostAndUsesBaseScheme(): void
    {
        $html = '<img src="//cdn.example.org/img.png"><link rel="stylesheet" href="//cdn.example.org/a.css">';
        $base = 'https://example.com/dir/';
        $out = $this->rewriter->addAbsoluteResources($html, $base);

        $this->assertStringContainsString('src="https://cdn.example.org/img.png"', $out);
        $this->assertStringContainsString('href="https://cdn.example.org/a.css"', $out);
    }

    public function testRewritesSrcsetCandidates(): void
    {
        $html = '<img srcset="/img/a.jpg 1x, /img/b.jpg 2x, https://other/x.jpg 3x">';
        $base = 'http://site.test/sub/path/';
        $out = $this->rewriter->addAbsoluteResources($html, $base);

        $this->assertStringContainsString(
            'srcset="http://site.test/img/a.jpg 1x, http://site.test/img/b.jpg 2x, https://other/x.jpg 3x"',
            $out
        );
    }

    public function testRewritesCssUrlsAndImportsIncludingStyleAttribute(): void
    {
        $html = '<style>
                body { background-image: url("../img/bg.png"); }
                @import url("/css/reset.css");
                @import "css/theme.css";
            </style>
            <div style="background: url(icons/ico.svg) no-repeat;">X</div>
        ';

        $base = 'https://ex.am/dir/level/page.html';
        $out = $this->rewriter->addAbsoluteResources($html, $base);

        $this->assertMatchesRegularExpression(
            '~url\((["\']?)https://ex\.am/dir/img/bg\.png\1\)~',
            $out
        );

        $this->assertMatchesRegularExpression(
            '~@import\s+(?:url\()?(["\']?)https://ex\.am/css/reset\.css\1\)?~',
            $out
        );

        $this->assertMatchesRegularExpression(
            '~@import\s+(?:url\()?(["\']?)https://ex\.am/dir/level/css/theme\.css\1\)?~',
            $out
        );

        $this->assertMatchesRegularExpression(
            '~url\((["\']?)https://ex\.am/dir/level/icons/ico\.svg\1\)~',
            $out
        );
    }

    public function testAbsolutizeUrlDirectlyCoversDotSegmentsAndPort(): void
    {
        $base = 'http://example.com:8080/a/b/c/';

        $this->assertSame(
            'http://example.com:8080/a/b/img.png',
            $this->rewriter->absolutizeUrl('../img.png', $base)
        );

        $this->assertSame(
            'http://example.com:8080/a/b/c/d/e.png?x=1#top',
            $this->rewriter->absolutizeUrl('d/./e.png?x=1#top', $base)
        );
    }
}
