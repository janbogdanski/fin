<?php

declare(strict_types=1);

namespace App\BrokerImport\Domain\Exception;

final class ImportRowLimitExceededException extends \RuntimeException
{
    public function __construct(
        public readonly int $rowCount,
        public readonly int $limit,
        string $brokerId,
    ) {
        parent::__construct(
            sprintf(
                'Import row limit exceeded for broker "%s": %d rows, limit is %d.',
                $brokerId,
                $rowCount,
                $limit,
            ),
        );
    }
}
