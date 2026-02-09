<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Common;

use PhpList\Core\Domain\Common\PdfGenerator;
use PHPUnit\Framework\TestCase;

class PdfGeneratorTest extends TestCase
{
    public function testCreatePdfBytesProducesNonEmptyPdfWithHeaderAndEof(): void
    {
        $generator = new PdfGenerator();
        $text = 'Hello PDF! こんにちは Привет';

        $pdfBytes = $generator->createPdfBytes($text);

        $this->assertIsString($pdfBytes);
        $this->assertNotSame('', $pdfBytes);

        // Must start with a valid PDF header
        $this->assertStringStartsWith('%PDF-', $pdfBytes);

        // Should contain EOF marker somewhere near the end
        $this->assertNotFalse(strpos($pdfBytes, '%%EOF'));

        // Should be reasonably sized for a minimal 1-page PDF
        $this->assertGreaterThan(100, strlen($pdfBytes));
    }

    public function testCreatePdfBytesContainsCreatorMetadataAndSomeText(): void
    {
        $generator = new PdfGenerator();
        $text = 'Sample text for pdfList PDF';

        $pdfBytes = $generator->createPdfBytes($text);

        // FPDF stores the Creator metadata; value set to 'phpList' in PdfGenerator
        $this->assertNotFalse(strpos($pdfBytes, 'phpList'));

        // The plain text often appears within a text object; ensure at least a fragment is present
        $fragment = 'Sample text';
        $this->assertNotFalse(strpos($pdfBytes, $fragment));
    }
}
