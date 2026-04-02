<?php

declare(strict_types=1);

namespace App\BrokerImport\Infrastructure\Adapter;

/**
 * Sanitizes CSV field values to prevent CSV injection.
 *
 * Strips leading characters that could trigger formula execution
 * in spreadsheet software (=, +, -, @, tab, carriage return, newline).
 *
 * @see https://owasp.org/www-community/attacks/CSV_Injection
 */
trait CsvSanitizer
{
    /**
     * Strips UTF-8 BOM (EF BB BF) from the beginning of content.
     *
     * CSV files exported from Windows applications (Excel, etc.) often
     * start with a UTF-8 BOM. If not stripped, the first header becomes
     * "\xEF\xBB\xBFDate" instead of "Date", breaking header detection.
     */
    private function stripBom(string $content): string
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            return substr($content, 3);
        }

        return $content;
    }

    private function sanitize(string $value): string
    {
        // Preserve negative numbers: dash followed by a digit is a numeric value, not a formula
        if (str_starts_with($value, '-') && isset($value[1]) && ($value[1] === '.' || ctype_digit($value[1]))) {
            return $value;
        }

        return ltrim($value, "=+-@\t\r\n");
    }
}
