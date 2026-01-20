<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common;

use FPDF;

class PdfGenerator
{
    public function createPdfBytes(string $text): string
    {
        $pdf = new FPDF();
        $pdf->SetCreator('phpList');
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 12);
        $pdf->Write(6, $text);

        return $pdf->Output('', 'S');
    }
}
