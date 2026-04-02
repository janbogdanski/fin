<?php

declare(strict_types=1);

namespace App\BrokerImport\Infrastructure\Adapter;

use App\BrokerImport\Application\DTO\NormalizedTransaction;
use App\BrokerImport\Application\DTO\ParseError;
use App\BrokerImport\Application\DTO\ParseMetadata;
use App\BrokerImport\Application\DTO\ParseResult;
use App\BrokerImport\Application\DTO\ParseWarning;

/**
 * Shared helper for building ParseResult with date range detection.
 *
 * Used by broker adapters to avoid duplicating the same
 * date-from/date-to iteration in every adapter.
 *
 * Requires the using class to implement brokerId().
 */
trait ParseResultBuilder
{
    /**
     * @param list<NormalizedTransaction> $transactions
     * @param list<ParseError> $errors
     * @param list<ParseWarning> $warnings
     * @param list<string> $sectionsFound
     */
    private function buildParseResult(
        array $transactions,
        array $errors,
        array $warnings,
        array $sectionsFound,
    ): ParseResult {
        $dateFrom = null;
        $dateTo = null;

        foreach ($transactions as $tx) {
            if ($dateFrom === null || $tx->date < $dateFrom) {
                $dateFrom = $tx->date;
            }

            if ($dateTo === null || $tx->date > $dateTo) {
                $dateTo = $tx->date;
            }
        }

        return new ParseResult(
            transactions: $transactions,
            errors: $errors,
            warnings: $warnings,
            metadata: new ParseMetadata(
                broker: $this->brokerId(),
                totalTransactions: count($transactions),
                totalErrors: count($errors),
                dateFrom: $dateFrom,
                dateTo: $dateTo,
                sectionsFound: $sectionsFound,
            ),
        );
    }
}
