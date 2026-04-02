<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Canonical timezone for all Polish tax-relevant date operations.
 *
 * Polish tax law operates in Europe/Warsaw timezone.
 * All DateTimeImmutable objects used in tax calculations,
 * NBP rate lookups, and working day resolution must use this timezone.
 */
final class PolishTimezone
{
    private static ?\DateTimeZone $instance = null;

    public static function get(): \DateTimeZone
    {
        return self::$instance ??= new \DateTimeZone('Europe/Warsaw');
    }
}
