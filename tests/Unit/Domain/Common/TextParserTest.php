<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Common;

use PhpList\Core\Domain\Common\TextParser;
use PHPUnit\Framework\TestCase;

class TextParserTest extends TestCase
{
    private TextParser $parser;

    protected function setUp(): void
    {
        $this->parser = new TextParser();
    }

    public function testEmailIsMadeClickable(): void
    {
        $input = 'Contact me at foo.bar-1@example.co.uk';
        $out = ($this->parser)($input);

        $this->assertSame(
            'Contact me at <a href="mailto:foo.bar-1%40example.co.uk" class="email">foo.bar-1@example.co.uk</a>',
            $out
        );
    }

    public function testHttpUrlAutoLinkAndPeriodOutside(): void
    {
        $input = 'See http://example.com/path.';
        $out = ($this->parser)($input);

        // For non-www URLs, the displayed text is without the scheme
        $this->assertSame(
            'See <a href="http://example.com/path" class="url" target="_blank">example.com/path</a>.',
            $out
        );
    }

    public function testWwwAutoLink(): void
    {
        $input = 'Visit www.google.com/maps';
        $out = ($this->parser)($input);

        $this->assertSame(
            'Visit <a href="http://www.google.com/maps" class="url" target="_blank">www.google.com/maps</a>',
            $out
        );
    }

    public function testNewlinesBecomeBrAndLeadingTrim(): void
    {
        // leading newline should be trimmed, others converted
        $input = "\nLine1\nLine2";
        $out = ($this->parser)($input);

        $this->assertSame("Line1<br />\nLine2", $out);
    }

    public function testParensAndDollarPreserved(): void
    {
        $input = 'Price is $10 (approx)';
        $out = ($this->parser)($input);

        $this->assertSame('Price is $10 (approx)', $out);
    }
}
