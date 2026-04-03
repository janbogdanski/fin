<?php

declare(strict_types=1);

namespace App\Declaration\Infrastructure\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Renders HTML content to PDF using DomPDF.
 *
 * Infrastructure adapter -- wraps the DomPDF library behind a simple interface.
 * Configuration: A4 portrait, UTF-8, remote resources disabled for security.
 */
final class DompdfPdfRenderer
{
    public function render(string $html): string
    {
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output() ?? '';
    }
}
