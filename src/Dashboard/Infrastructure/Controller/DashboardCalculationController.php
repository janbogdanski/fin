<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure\Controller;

use App\BrokerImport\Application\Port\ImportedTransactionRepositoryInterface;
use App\Dashboard\Infrastructure\Service\SourceTransactionLookup;
use App\Shared\Infrastructure\Controller\ResolvesCurrentUser;
use App\TaxCalc\Application\Port\ClosedPositionQueryPort;
use App\TaxCalc\Application\Query\GetTaxSummary;
use App\TaxCalc\Application\Query\GetTaxSummaryHandler;
use App\TaxCalc\Application\Query\TaxSummaryResult;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\BigDecimal;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardCalculationController extends AbstractController
{
    use ResolvesCurrentUser;

    public function __construct(
        private readonly GetTaxSummaryHandler $taxSummaryHandler,
        private readonly ImportedTransactionRepositoryInterface $importedTxRepo,
        private readonly ClosedPositionQueryPort $closedPositionQuery,
        private readonly SourceTransactionLookup $sourceTransactionLookup,
    ) {
    }

    #[Route('/dashboard/calculation/{taxYear}', name: 'dashboard_calculation', methods: ['GET'], requirements: [
        'taxYear' => '\d{4}',
    ])]
    public function __invoke(int $taxYear): Response
    {
        $userId = $this->resolveUserId();
        $isEmpty = $this->importedTxRepo->countByUser($userId) === 0;

        $summary = $isEmpty
            ? $this->getEmptySummary($taxYear)
            : ($this->taxSummaryHandler)(new GetTaxSummary($userId, TaxYear::of($taxYear)));

        $txRows = [];
        $brokers = [];

        if (! $isEmpty) {
            $closedPositions = $this->closedPositionQuery->findByUserYearAndCategory(
                $userId,
                TaxYear::of($taxYear),
                TaxCategory::EQUITY,
            );
            $transactionIds = [];

            foreach ($closedPositions as $closedPosition) {
                $transactionIds[] = $closedPosition->buyTransactionId->toString();
                $transactionIds[] = $closedPosition->sellTransactionId->toString();
            }

            $transactionLookup = $this->sourceTransactionLookup->findByUserAndIds($userId, $transactionIds);

            foreach ($closedPositions as $cp) {
                $buyTransaction = $transactionLookup[$cp->buyTransactionId->toString()] ?? null;
                $sellTransaction = $transactionLookup[$cp->sellTransactionId->toString()] ?? null;
                $buyBroker = $cp->buyBroker->toString();
                $sellBroker = $cp->sellBroker->toString();
                $symbol = $buyTransaction['symbol'] ?? ($sellTransaction['symbol'] ?? $cp->isin->toString());

                $txRows[] = [
                    'buyDate' => $cp->buyDate->format('Y-m-d'),
                    'sellDate' => $cp->sellDate->format('Y-m-d'),
                    'instrument' => $symbol . ' ' . $cp->isin->toString(),
                    'symbol' => $symbol,
                    'isin' => $cp->isin->toString(),
                    'quantity' => (string) $cp->quantity,
                    'buyBroker' => $buyBroker,
                    'sellBroker' => $sellBroker,
                    'buyPriceOriginal' => $buyTransaction['price'] ?? '',
                    'buyCurrency' => $buyTransaction['currency'] ?? '',
                    'sellPriceOriginal' => $sellTransaction['price'] ?? '',
                    'sellCurrency' => $sellTransaction['currency'] ?? '',
                    'type' => 'FIFO_MATCH',
                    'broker' => $buyBroker === $sellBroker ? $buyBroker : $buyBroker . '|' . $sellBroker,
                    'sortDate' => $cp->sellDate->format('Y-m-d'),
                    'nbpRate' => (string) $cp->sellNBPRate->rate(),
                    'buyNbpRate' => (string) $cp->buyNBPRate->rate(),
                    'sellNbpRate' => (string) $cp->sellNBPRate->rate(),
                    'costBasisPLN' => (string) $cp->costBasisPLN,
                    'proceedsPLN' => (string) $cp->proceedsPLN,
                    'gainLossPLN' => (string) $cp->gainLossPLN,
                ];
                $brokers[$buyBroker] = true;
                $brokers[$sellBroker] = true;
            }
        }

        $costsWithCommissions = BigDecimal::of($summary->equityCostBasis)->plus(BigDecimal::of($summary->equityCommissions));

        return $this->render('dashboard/calculation.html.twig', [
            'isEmpty' => $isEmpty,
            'summary' => $summary,
            'costsWithCommissions' => (string) $costsWithCommissions,
            'transactions' => $txRows,
            'brokers' => array_keys($brokers),
        ]);
    }

    private function getEmptySummary(int $taxYear): TaxSummaryResult
    {
        return new TaxSummaryResult(
            taxYear: $taxYear,
            equityProceeds: '0.00',
            equityCostBasis: '0.00',
            equityCommissions: '0.00',
            equityGainLoss: '0.00',
            equityLossDeduction: '0.00',
            equityTaxableIncome: '0.00',
            equityTax: '0.00',
            dividendsByCountry: [],
            dividendTotalTaxDue: '0.00',
            cryptoProceeds: '0.00',
            cryptoCostBasis: '0.00',
            cryptoCommissions: '0.00',
            cryptoGainLoss: '0.00',
            cryptoLossDeduction: '0.00',
            cryptoTaxableIncome: '0.00',
            cryptoTax: '0.00',
            totalTaxDue: '0.00',
        );
    }
}
