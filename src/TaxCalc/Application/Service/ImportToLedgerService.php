<?php

declare(strict_types=1);

namespace App\TaxCalc\Application\Service;

use App\BrokerImport\Application\DTO\NormalizedTransaction;
use App\BrokerImport\Application\DTO\TransactionType;
use App\BrokerImport\Application\Port\FifoProcessorPort;
use App\ExchangeRate\Application\Port\ExchangeRateProviderInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\NBPRate;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Port\InstrumentKeyResolverInterface;
use App\TaxCalc\Domain\Exception\InsufficientSharesException;
use App\TaxCalc\Domain\Model\ClosedPosition;
use App\TaxCalc\Domain\Model\TaxPositionLedger;
use App\TaxCalc\Domain\Repository\TaxPositionLedgerRepositoryInterface;
use App\TaxCalc\Domain\Service\CurrencyConverterInterface;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\BigDecimal;
use Psr\Log\LoggerInterface;

/**
 * Translates imported CSV data into FIFO-matched tax positions.
 *
 * Takes NormalizedTransactions from broker imports, groups by instrument key
 * (resolved via InstrumentKeyResolverInterface — ISIN when present, ticker symbol
 * as fallback for brokers such as XTB that do not export ISINs), rebuilds
 * TaxPositionLedger per instrument from the full transaction history,
 * registers buy/sell with NBP rates, and persists results to DB.
 *
 * When called with persist=true, saves ledgers and closed positions to DB.
 * When called with persist=false (e.g. for preview), operates in-memory only.
 */
final readonly class ImportToLedgerService implements FifoProcessorPort
{
    /**
     * PLN identity rate -- used when transaction currency is PLN (no conversion needed).
     * NBPRate requires a valid table number format, so we use a synthetic one.
     */
    private const string PLN_IDENTITY_TABLE = '001/A/NBP/2025';

    public function __construct(
        private CurrencyConverterInterface $currencyConverter,
        private ExchangeRateProviderInterface $exchangeRateProvider,
        private TaxPositionLedgerRepositoryInterface $ledgerRepository,
        private LoggerInterface $logger,
        private InstrumentKeyResolverInterface $keyResolver = new IsinWithSymbolFallbackKeyResolver(),
    ) {
    }

    /**
     * Process transactions and persist results to DB.
     *
     * @param list<NormalizedTransaction> $transactions all imported transactions (any year)
     * @param UserId $userId current user
     * @param TaxYear $taxYear target tax year for which to report closed positions
     * @param bool $persist if true, saves ledgers + closed positions to DB
     */
    public function process(
        array $transactions,
        UserId $userId,
        TaxYear $taxYear,
        bool $persist = false,
    ): LedgerProcessingResult {
        $buyAndSell = $this->filterBuySellTransactions($transactions);

        usort($buyAndSell, static fn (NormalizedTransaction $a, NormalizedTransaction $b): int => $a->date <=> $b->date);

        $grouped = $this->groupByInstrument($buyAndSell);

        $allClosedPositions = [];
        $errors = [];

        if ($persist) {
            // The caller passes the full transaction history, so we refresh the year's
            // append-only audit trail before persisting a clean recomputation.
            $this->ledgerRepository->deleteClosedPositionsForUserAndYear($userId, $taxYear);
        }

        foreach ($grouped as $isinString => $isinTransactions) {
            $isin = ISIN::fromUnchecked($isinString);

            // Rebuild from the complete history to keep replay idempotent.
            $ledger = TaxPositionLedger::create($userId, $isin, TaxCategory::EQUITY);

            foreach ($isinTransactions as $tx) {
                try {
                    $nbpRate = $this->resolveNBPRate($tx);
                    $commission = $this->resolveCommission($tx);

                    if ($tx->type === TransactionType::BUY) {
                        $ledger->registerBuy(
                            $tx->id,
                            $tx->date,
                            $tx->quantity,
                            $tx->pricePerUnit,
                            $commission,
                            $tx->broker,
                            $nbpRate,
                            $this->currencyConverter,
                        );
                    } elseif ($tx->type === TransactionType::SELL) {
                        $closedPositions = $ledger->registerSell(
                            $tx->id,
                            $tx->date,
                            $tx->quantity,
                            $tx->pricePerUnit,
                            $commission,
                            $tx->broker,
                            $nbpRate,
                            $this->currencyConverter,
                        );

                        $yearFiltered = $this->filterByTaxYear($closedPositions, $taxYear);
                        $allClosedPositions = array_merge($allClosedPositions, $yearFiltered);
                    }
                } catch (InsufficientSharesException $e) {
                    $errors[] = sprintf(
                        'ISIN %s: %s',
                        $isinString,
                        $e->getMessage(),
                    );
                } catch (\RuntimeException $e) {
                    $errors[] = sprintf(
                        'ISIN %s, data %s: %s',
                        $isinString,
                        $tx->date->format('Y-m-d'),
                        $e->getMessage(),
                    );
                }
            }

            if ($persist) {
                $this->ledgerRepository->save($ledger);
            }
        }

        $result = new LedgerProcessingResult(
            closedPositions: array_values($allClosedPositions),
            errors: $errors,
        );

        if ($persist) {
            $this->logger->info('ImportToLedger: processed {count} closed positions for user {userId}, year {year}', [
                'count' => \count($result->closedPositions),
                'userId' => $userId->toString(),
                'year' => $taxYear->value,
            ]);
        }

        return $result;
    }

    /**
     * @param list<NormalizedTransaction> $transactions
     * @return list<NormalizedTransaction>
     */
    private function filterBuySellTransactions(array $transactions): array
    {
        return array_values(array_filter(
            $transactions,
            fn (NormalizedTransaction $tx): bool => $this->keyResolver->resolveKey($tx) !== null
                && ($tx->type === TransactionType::BUY || $tx->type === TransactionType::SELL),
        ));
    }

    /**
     * Groups transactions by their resolved instrument key.
     *
     * The key is determined by the injected InstrumentKeyResolverInterface strategy,
     * allowing different brokers to use different identification schemes (ISIN, symbol, etc.).
     *
     * @param list<NormalizedTransaction> $transactions already filtered to BUY/SELL with resolvable key
     * @return array<string, list<NormalizedTransaction>>
     */
    private function groupByInstrument(array $transactions): array
    {
        $grouped = [];

        foreach ($transactions as $tx) {
            // resolveKey cannot return null here — filterBuySellTransactions guarantees it.
            $key = $this->keyResolver->resolveKey($tx);
            assert($key !== null);
            $grouped[$key][] = $tx;
        }

        return $grouped;
    }

    private function resolveNBPRate(NormalizedTransaction $tx): NBPRate
    {
        $currency = $tx->pricePerUnit->currency();

        if ($currency->equals(CurrencyCode::PLN)) {
            return NBPRate::create(
                CurrencyCode::PLN,
                BigDecimal::of('1.0000'),
                $tx->date,
                self::PLN_IDENTITY_TABLE,
            );
        }

        return $this->exchangeRateProvider->getRateForDate($currency, $tx->date);
    }

    /**
     * Resolves commission for use with the price-currency NBP rate.
     *
     * Degiro (and some other brokers) charge commissions in a different currency
     * than the traded instrument (e.g. EUR commission on a USD stock).
     * In such cases we pre-convert the commission to PLN using its own NBP rate,
     * because CurrencyConverter.toPLN() requires currency(Money) == currency(NBPRate).
     * CurrencyConverter short-circuits for PLN, so the pre-converted PLN value
     * passes through registerBuy/registerSell transparently.
     */
    private function resolveCommission(NormalizedTransaction $tx): Money
    {
        $commission = $tx->commission;
        $commissionCurrency = $commission->currency();
        $priceCurrency = $tx->pricePerUnit->currency();

        if ($commissionCurrency->equals($priceCurrency) || $commissionCurrency->equals(CurrencyCode::PLN)) {
            return $commission;
        }

        $commissionRate = $this->exchangeRateProvider->getRateForDate($commissionCurrency, $tx->date);

        return $this->currencyConverter->toPLN($commission, $commissionRate);
    }

    /**
     * @param list<ClosedPosition> $closedPositions
     * @return list<ClosedPosition>
     */
    private function filterByTaxYear(array $closedPositions, TaxYear $taxYear): array
    {
        return array_values(array_filter(
            $closedPositions,
            static fn (ClosedPosition $cp): bool => (int) $cp->sellDate->format('Y') === $taxYear->value,
        ));
    }
}
