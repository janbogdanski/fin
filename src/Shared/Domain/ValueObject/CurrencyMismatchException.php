<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

final class CurrencyMismatchException extends \DomainException
{
    public function __construct(CurrencyCode $expected, CurrencyCode $actual)
    {
        parent::__construct(
            "Currency mismatch: expected {$expected->value}, got {$actual->value}",
        );
    }
}
