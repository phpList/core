<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common;

use FPDF;

class PdfGenerator
{
    public function createPdfBytes(string $text): string
    {
        $pdf = new FPDF();
        // Disable compression to ensure plain text and metadata are visible in output (helps testing)
        if (method_exists($pdf, 'SetCompression')) {
            $pdf->SetCompression(false);
        }
        $pdf->SetCreator('phpList');
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 12);
        $pdf->Write(6, $text);

        return $pdf->Output('', 'S');
    }
}
