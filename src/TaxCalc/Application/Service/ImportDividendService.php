<?php

declare(strict_types=1);

namespace App\TaxCalc\Application\Service;

use App\BrokerImport\Application\DTO\NormalizedTransaction;
use App\BrokerImport\Application\DTO\TransactionType;
use App\BrokerImport\Application\Port\DividendProcessorPort;
use App\ExchangeRate\Application\Port\ExchangeRateProviderInterface;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\NBPRate;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Port\DividendResultRepositoryPort;
use App\TaxCalc\Domain\Service\DividendTaxService;
use App\TaxCalc\Domain\ValueObject\DividendTaxResult;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Processes DIVIDEND + WITHHOLDING_TAX transactions from CSV import.
 *
 * Pairs dividends with their WHT, calculates tax via DividendTaxService,
 * and persists results to DB. Dedup: deletes existing results for user+year
 * before saving (idempotent re-import).
 *
 * Country resolution: derived from ISIN prefix (first 2 chars = ISO 3166-1 alpha-2).
 *
 * @see art. 30a ust. 1 pkt 4 ustawy o PIT
 */
final class ImportDividendService implements DividendProcessorPort
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly DividendTaxService $dividendTaxService,
        private readonly ExchangeRateProviderInterface $exchangeRateProvider,
        private readonly DividendResultRepositoryPort $repository,
        private readonly Connection $connection,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Process all transactions, extract DIVIDEND+WHT, calculate tax, persist.
     *
     * @param list<NormalizedTransaction> $transactions all imported transactions (any type)
     * @param UserId $userId current user
     * @param TaxYear $taxYear target tax year
     * @return list<DividendTaxResult> computed results for the target year
     */
    public function process(
        array $transactions,
        UserId $userId,
        TaxYear $taxYear,
    ): array {
        $dividends = $this->filterByTypeAndYear($transactions, TransactionType::DIVIDEND, $taxYear);
        $whts = $this->filterByTypeAndYear($transactions, TransactionType::WITHHOLDING_TAX, $taxYear);

        $whtByKey = $this->indexWhtByMatchKey($whts);

        $results = [];

        foreach ($dividends as $dividend) {
            $matchKey = $this->buildMatchKey($dividend);
            $whtAmount = $whtByKey[$matchKey] ?? BigDecimal::zero();

            $grossAmount = $dividend->pricePerUnit->amount()->multipliedBy($dividend->quantity);
            $whtRate = $grossAmount->isZero()
                ? BigDecimal::zero()
                : $whtAmount->dividedBy($grossAmount, 6, RoundingMode::HALF_UP);

            $nbpRate = $this->resolveNBPRate($dividend);
            $country = $this->resolveCountry($dividend);

            if ($country === null) {
                continue;
            }

            $result = $this->dividendTaxService->calculate(
                grossDividend: $dividend->pricePerUnit->multiply($dividend->quantity),
                nbpRate: $nbpRate,
                sourceCountry: $country,
                actualWHTRate: $whtRate,
            );

            $results[] = $result;
        }

        // Dedup: delete existing, then save fresh batch (atomic)
        $this->connection->transactional(function () use ($userId, $taxYear, $results): void {
            $this->repository->deleteByUserAndYear($userId, $taxYear);
            $this->repository->saveAll($userId, $taxYear, $results);
        });

        return $results;
    }

    /**
     * @param list<NormalizedTransaction> $transactions
     * @return list<NormalizedTransaction>
     */
    private function filterByTypeAndYear(
        array $transactions,
        TransactionType $type,
        TaxYear $taxYear,
    ): array {
        return array_values(array_filter(
            $transactions,
            static fn (NormalizedTransaction $tx): bool => $tx->type === $type
                && (int) $tx->date->format('Y') === $taxYear->value,
        ));
    }

    /**
     * Index WHT amounts by match key (ISIN + date) for O(1) lookup.
     * Multiple WHT entries for same key are summed.
     *
     * @param list<NormalizedTransaction> $whts
     * @return array<string, BigDecimal>
     */
    private function indexWhtByMatchKey(array $whts): array
    {
        $indexed = [];

        foreach ($whts as $wht) {
            $key = $this->buildMatchKey($wht);
            $amount = $wht->pricePerUnit->amount()->multipliedBy($wht->quantity);
            $indexed[$key] = isset($indexed[$key])
                ? $indexed[$key]->plus($amount)
                : $amount;
        }

        return $indexed;
    }

    /**
     * Match key: ISIN + date (yyyy-mm-dd).
     * DIVIDEND and WHT from same broker for same security on same date are paired.
     */
    private function buildMatchKey(NormalizedTransaction $tx): string
    {
        $isin = $tx->isin?->toString() ?? 'UNKNOWN';

        return sprintf('%s_%s', $isin, $tx->date->format('Y-m-d'));
    }

    private function resolveNBPRate(NormalizedTransaction $tx): NBPRate
    {
        $currency = $tx->pricePerUnit->currency();

        if ($currency->equals(CurrencyCode::PLN)) {
            return NBPRate::create(
                CurrencyCode::PLN,
                BigDecimal::of('1.0000'),
                $tx->date,
                sprintf('001/A/NBP/%s', $tx->date->format('Y')),
            );
        }

        return $this->exchangeRateProvider->getRateForDate($currency, $tx->date);
    }

    /**
     * Resolve country from ISIN prefix.
     * ISIN first 2 chars = ISO 3166-1 alpha-2 country code.
     * Returns null if ISIN is missing (dividend skipped with warning).
     */
    private function resolveCountry(NormalizedTransaction $tx): ?CountryCode
    {
        if ($tx->isin === null) {
            $this->logger->warning('Skipping dividend: ISIN is null', [
                'date' => $tx->date->format('Y-m-d'),
                'symbol' => $tx->symbol,
                'description' => $tx->description,
            ]);

            return null;
        }

        $prefix = substr($tx->isin->toString(), 0, 2);

        return CountryCode::fromString($prefix);
    }
}
