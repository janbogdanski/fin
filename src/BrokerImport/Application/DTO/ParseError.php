<?php

declare(strict_types=1);

namespace App\BrokerImport\Application\DTO;

final readonly class ParseError
{
    public function __construct(
        public int $lineNumber,
        public string $section,
        public string $message,
        /**
         * @var array<string, string>
         */
        public array $rawData = [],
    ) {
    }
}
