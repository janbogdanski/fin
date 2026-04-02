<?php

declare(strict_types=1);

namespace App\BrokerImport\Application\DTO;

final readonly class ParseWarning
{
    public function __construct(
        public int $lineNumber,
        public string $section,
        public string $message,
    ) {
    }
}
