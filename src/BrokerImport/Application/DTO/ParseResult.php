<?php

declare(strict_types=1);

namespace App\BrokerImport\Application\DTO;

final readonly class ParseResult
{
    /**
     * @param list<NormalizedTransaction> $transactions
     * @param list<ParseError> $errors
     * @param list<ParseWarning> $warnings
     */
    public function __construct(
        public array $transactions,
        public array $errors,
        public array $warnings,
        public ParseMetadata $metadata,
    ) {
    }
}
