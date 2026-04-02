<?php

declare(strict_types=1);

namespace App\TaxCalc\Infrastructure\Controller;

use App\BrokerImport\Application\Port\ImportedTransactionRepositoryInterface;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\ValueObject\UserId;
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

#[Route('/dashboard')]
final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly GetTaxSummaryHandler $taxSummaryHandler,
        private readonly ImportedTransactionRepositoryInterface $importedTxRepo,
        private readonly ClosedPositionQueryPort $closedPositionQuery,
    ) {
    }

    #[Route('', name: 'dashboard_index', methods: ['GET'])]
    public function index(): Response
    {
        $userId = $this->resolveUserId();
        $taxYear = (int) date('Y') - 1;

        $isEmpty = $this->importedTxRepo->countByUser($userId) === 0;

        $summary = $isEmpty
            ? $this->getEmptySummary($taxYear)
            : ($this->taxSummaryHandler)(new GetTaxSummary($userId, TaxYear::of($taxYear)));

        return $this->render('dashboard/index.html.twig', [
            'isEmpty' => $isEmpty,
            'summary' => $summary,
            'availableYears' => [$taxYear, $taxYear - 1, $taxYear - 2],
        ]);
    }

    #[Route('/calculation/{taxYear}', name: 'dashboard_calculation', methods: ['GET'], requirements: [
        'taxYear' => '\d{4}',
    ])]
    public function calculation(int $taxYear): Response
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

            foreach ($closedPositions as $cp) {
                $txRows[] = [
                    'date' => $cp->sellDate->format('Y-m-d'),
                    'instrument' => $cp->isin->toString(),
                    'type' => 'SELL',
                    'quantity' => (string) $cp->quantity,
                    'priceOriginal' => (string) $cp->proceedsPLN->dividedBy($cp->quantity, 2, RoundingMode::HALF_UP),
                    'currency' => 'PLN',
                    'nbpRate' => (string) $cp->sellNBPRate->rate(),
                    'gainLossPLN' => (string) $cp->gainLossPLN,
                    'broker' => $cp->sellBroker->toString(),
                ];
                $brokers[$cp->sellBroker->toString()] = true;
            }
        }

        return $this->render('dashboard/calculation.html.twig', [
            'isEmpty' => $isEmpty,
            'summary' => $summary,
            'transactions' => $txRows,
            'brokers' => array_keys($brokers),
        ]);
    }

    #[Route('/fifo/{taxYear}', name: 'dashboard_fifo', methods: ['GET'], requirements: [
        'taxYear' => '\d{4}',
    ])]
    public function fifo(int $taxYear): Response
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

    #[Route('/dividends/{taxYear}', name: 'dashboard_dividends', methods: ['GET'], requirements: [
        'taxYear' => '\d{4}',
    ])]
    public function dividends(int $taxYear): Response
    {
        $userId = $this->resolveUserId();
        $isEmpty = $this->importedTxRepo->countByUser($userId) === 0;

        $summary = $isEmpty
            ? $this->getEmptySummary($taxYear)
            : ($this->taxSummaryHandler)(new GetTaxSummary($userId, TaxYear::of($taxYear)));

        return $this->render('dashboard/dividends.html.twig', [
            'isEmpty' => $isEmpty,
            'summary' => $summary,
        ]);
    }

    private function resolveUserId(): UserId
    {
        /** @var SecurityUser $user */
        $user = $this->getUser();

        return UserId::fromString($user->id());
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
