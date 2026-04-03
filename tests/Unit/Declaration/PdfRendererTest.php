<?php

declare(strict_types=1);

namespace App\Tests\Unit\Declaration;

use App\Declaration\Infrastructure\Pdf\DompdfPdfRenderer;
use PHPUnit\Framework\TestCase;

final class PdfRendererTest extends TestCase
{
    public function testRenderReturnsPdfBytes(): void
    {
        $renderer = new DompdfPdfRenderer();

        $html = '<html><body><h1>Test PDF</h1></body></html>';
        $pdf = $renderer->render($html);

        self::assertNotEmpty($pdf);
        // PDF files start with %PDF magic bytes
        self::assertStringStartsWith('%PDF', $pdf);
    }

    public function testRenderWithComplexHtmlProducesPdf(): void
    {
        $renderer = new DompdfPdfRenderer();

        $html = <<<'HTML'
        <!DOCTYPE html>
        <html lang="pl">
        <head>
            <meta charset="UTF-8">
            <title>Raport audytowy</title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 10px; }
                table { border-collapse: collapse; width: 100%; }
                th, td { border: 1px solid #ccc; padding: 4px; }
            </style>
        </head>
        <body>
            <h1>Raport audytowy PIT-38</h1>
            <table>
                <tr><th>ISIN</th><th>Zysk</th></tr>
                <tr><td>US0378331005</td><td>10142.00</td></tr>
            </table>
        </body>
        </html>
        HTML;

        $pdf = $renderer->render($html);

        self::assertStringStartsWith('%PDF', $pdf);
        self::assertGreaterThan(1000, strlen($pdf), 'PDF should be non-trivial size');
    }

    public function testRenderEmptyHtmlProducesPdf(): void
    {
        $renderer = new DompdfPdfRenderer();

        $pdf = $renderer->render('<html><body></body></html>');

        self::assertStringStartsWith('%PDF', $pdf);
    }
}
