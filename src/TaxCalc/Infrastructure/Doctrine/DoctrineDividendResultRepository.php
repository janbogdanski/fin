<?php

declare(strict_types=1);

namespace App\TaxCalc\Infrastructure\Doctrine;

use App\Shared\Domain\Port\GdprDataErasurePort;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Port\DividendResultRepositoryPort;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\RoundingMode;
use Doctrine\DBAL\Connection;

/**
 * Doctrine DBAL implementation of DividendResultRepositoryPort.
 * Persists computed dividend tax results to the dividend_tax_results table.
 */
final readonly class DoctrineDividendResultRepository implements DividendResultRepositoryPort, GdprDataErasurePort
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function deleteByUser(UserId $userId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM dividend_tax_results WHERE user_id = :userId',
            [
                'userId' => $userId->toString(),
            ],
        );
    }

    public function deleteByUserAndYear(UserId $userId, TaxYear $taxYear): void
    {
        $this->connection->executeStatement(
            'DELETE FROM dividend_tax_results WHERE user_id = :userId AND tax_year = :taxYear',
            [
                'userId' => $userId->toString(),
                'taxYear' => $taxYear->value,
            ],
        );
    }

    public function saveAll(UserId $userId, TaxYear $taxYear, array $results): void
    {
        foreach ($results as $result) {
            $this->connection->insert('dividend_tax_results', [
                'user_id' => $userId->toString(),
                'tax_year' => $taxYear->value,
                'country_code' => $result->sourceCountry->value,
                'gross_pln' => $result->grossDividendPLN->amount()->toScale(8, RoundingMode::HALF_UP)->__toString(),
                'wht_pln' => $result->whtPaidPLN->amount()->toScale(8, RoundingMode::HALF_UP)->__toString(),
                'tax_due_pln' => $result->polishTaxDue->amount()->toScale(8, RoundingMode::HALF_UP)->__toString(),
                'wht_rate' => $result->whtRate->toScale(6, RoundingMode::HALF_UP)->__toString(),
                'upo_rate' => $result->upoRate->toScale(6, RoundingMode::HALF_UP)->__toString(),
                'nbp_rate_date' => $result->nbpRate->effectiveDate()->format('Y-m-d'),
                'nbp_rate_value' => $result->nbpRate->rate()->toScale(8, RoundingMode::HALF_UP)->__toString(),
                'nbp_rate_table' => $result->nbpRate->tableNumber(),
                'nbp_rate_currency' => $result->nbpRate->currency()->value,
            ]);
        }
    }

    public function transactional(callable $callback): mixed
    {
        return $this->connection->transactional(static fn (): mixed => $callback());
    }
}
