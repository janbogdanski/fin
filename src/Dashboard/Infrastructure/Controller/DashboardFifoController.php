<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure\Controller;

use App\BrokerImport\Application\Port\ImportedTransactionRepositoryInterface;
use App\Shared\Infrastructure\Controller\ResolvesCurrentUser;
use App\TaxCalc\Application\Port\ClosedPositionQueryPort;
use App\TaxCalc\Application\Query\GetTaxSummary;
use App\TaxCalc\Application\Query\GetTaxSummaryHandler;
use App\TaxCalc\Application\Query\TaxSummaryResult;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\RoundingMode;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardFifoController extends AbstractController
{
    use ResolvesCurrentUser;

    public function __construct(
        private readonly GetTaxSummaryHandler $taxSummaryHandler,
        private readonly ImportedTransactionRepositoryInterface $importedTxRepo,
        private readonly ClosedPositionQueryPort $closedPositionQuery,
    ) {
    }

    #[Route('/dashboard/fifo/{taxYear}', name: 'dashboard_fifo', methods: ['GET'], requirements: [
        'taxYear' => '\d{4}',
    ])]
    public function __invoke(int $taxYear): Response
    {
        $userId = $this->resolveUserId();
        $isEmpty = $this->importedTxRepo->countByUser($userId) === 0;

        $summary = $isEmpty
            ? $this->getEmptySummary($taxYear)
            : ($this->taxSummaryHandler)(new GetTaxSummary($userId, TaxYear::of($taxYear)));

        $instruments = [];

        if (! $isEmpty) {
            $closedPositions = $this->closedPositionQuery->findByUserYearAndCategory(
                $userId,
                TaxYear::of($taxYear),
                TaxCategory::EQUITY,
            );

            foreach ($closedPositions as $cp) {
                $isinKey = $cp->isin->toString();
                $instruments[$isinKey][] = [
                    'buyDate' => $cp->buyDate->format('Y-m-d'),
                    'buyPrice' => (string) $cp->costBasisPLN->dividedBy($cp->quantity, 2, RoundingMode::HALF_UP),
                    'buyNbpRate' => (string) $cp->buyNBPRate->rate(),
                    'sellDate' => $cp->sellDate->format('Y-m-d'),
                    'sellPrice' => (string) $cp->proceedsPLN->dividedBy($cp->quantity, 2, RoundingMode::HALF_UP),
                    'sellNbpRate' => (string) $cp->sellNBPRate->rate(),
                    'quantity' => (string) $cp->quantity,
                    'costBasisPLN' => (string) $cp->costBasisPLN,
                    'proceedsPLN' => (string) $cp->proceedsPLN,
                    'gainLossPLN' => (string) $cp->gainLossPLN,
                ];
            }
        }

        return $this->render('dashboard/fifo.html.twig', [
            'isEmpty' => $isEmpty,
            'summary' => $summary,
            'instruments' => $instruments,
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
