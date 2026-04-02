<?php

declare(strict_types=1);

namespace App\BrokerImport\Infrastructure\Adapter;

/**
 * Sanitizes CSV field values to prevent CSV injection.
 *
 * Strips leading characters that could trigger formula execution
 * in spreadsheet software (=, +, -, @, tab, carriage return).
 *
 * @see https://owasp.org/www-community/attacks/CSV_Injection
 */
trait CsvSanitizer
{
    private function sanitize(string $value): string
    {
        // Preserve negative numbers: dash followed by a digit is a numeric value, not a formula
        if (str_starts_with($value, '-') && isset($value[1]) && ($value[1] === '.' || ctype_digit($value[1]))) {
            return $value;
        }

        return ltrim($value, "=+-@\t\r");
    }
}
