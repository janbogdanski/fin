<?php

declare(strict_types=1);

namespace App\TaxCalc\Domain\Service;

use App\TaxCalc\Domain\ValueObject\TaxCategory;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;

/**
 * Validates prior year loss form input.
 *
 * Encapsulates amount parsing and range checks so that the controller
 * only needs to handle HTTP concerns (rate limiting, CSRF, redirect).
 *
 * Returns a result value object to avoid throwing exceptions for user input errors.
 */
final class LossFormValidator
{
    /**
     * Maximum allowed loss amount in PLN (100 million).
     */
    public const string MAX_LOSS_AMOUNT = '100000000';

    /**
     * Validates and parses the raw amount string from user input.
     *
     * @return array{ok: true, amount: BigDecimal}|array{ok: false, error: string}
     */
    public static function parseAmount(string $rawAmount): array
    {
        $normalized = trim(str_replace(',', '.', $rawAmount));

        try {
            $bigAmount = BigDecimal::of($normalized);
        } catch (MathException) {
            return [
                'ok' => false,
                'error' => 'Kwota straty musi byc liczba wieksza od zera.',
            ];
        }

        if ($bigAmount->isNegativeOrZero()) {
            return [
                'ok' => false,
                'error' => 'Kwota straty musi byc liczba wieksza od zera.',
            ];
        }

        if ($bigAmount->isGreaterThan(BigDecimal::of(self::MAX_LOSS_AMOUNT))) {
            return [
                'ok' => false,
                'error' => sprintf(
                    'Kwota straty nie moze przekraczac %s PLN.',
                    number_format((float) self::MAX_LOSS_AMOUNT, 0, '', ' '),
                ),
            ];
        }

        return [
            'ok' => true,
            'amount' => $bigAmount->toScale(2),
        ];
    }

    /**
     * Validates the tax category string from user input.
     */
    public static function parseCategory(string $rawCategory): ?TaxCategory
    {
        return TaxCategory::tryFrom($rawCategory);
    }
}
