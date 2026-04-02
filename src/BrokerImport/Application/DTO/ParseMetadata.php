<?php

declare(strict_types=1);

namespace App\BrokerImport\Application\DTO;

use App\Shared\Domain\ValueObject\BrokerId;

final readonly class ParseMetadata
{
    public function __construct(
        public BrokerId $broker,
        public int $totalTransactions,
        public int $totalErrors,
        public ?\DateTimeImmutable $dateFrom,
        public ?\DateTimeImmutable $dateTo,
        /**
         * @var list<string>
         */
        public array $sectionsFound,
    ) {
    }
}
