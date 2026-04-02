<?php

declare(strict_types=1);

namespace App\TaxCalc\Infrastructure\Doctrine;

use App\Shared\Domain\PolishTimezone;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\NBPRate;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Port\DividendResultQueryPort;
use App\TaxCalc\Domain\ValueObject\DividendTaxResult;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\BigDecimal;
use Doctrine\DBAL\Connection;

/**
 * Doctrine DBAL implementation of DividendResultQueryPort.
 * Reads persisted dividend tax results from the dividend_tax_results table.
 */
final readonly class DoctrineDividendResultQueryAdapter implements DividendResultQueryPort
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @return list<DividendTaxResult>
     */
    public function findByUserAndYear(UserId $userId, TaxYear $taxYear): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT * FROM dividend_tax_results
                WHERE user_id = :userId
                  AND tax_year = :taxYear
                ORDER BY country_code ASC
            SQL,
            [
                'userId' => $userId->toString(),
                'taxYear' => $taxYear->value,
            ],
        );

        return array_map($this->hydrateRow(...), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateRow(array $row): DividendTaxResult
    {
        return new DividendTaxResult(
            grossDividendPLN: Money::of($row['gross_pln'], CurrencyCode::PLN),
            whtPaidPLN: Money::of($row['wht_pln'], CurrencyCode::PLN),
            whtRate: BigDecimal::of($row['wht_rate']),
            upoRate: BigDecimal::of($row['upo_rate']),
            polishTaxDue: Money::of($row['tax_due_pln'], CurrencyCode::PLN),
            sourceCountry: CountryCode::from($row['country_code']),
            nbpRate: NBPRate::create(
                CurrencyCode::from($row['nbp_rate_currency']),
                BigDecimal::of($row['nbp_rate_value']),
                new \DateTimeImmutable($row['nbp_rate_date'], PolishTimezone::get()),
                $row['nbp_rate_table'],
            ),
        );
    }
}
