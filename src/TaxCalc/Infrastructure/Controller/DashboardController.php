<?php

declare(strict_types=1);

namespace App\TaxCalc\Infrastructure\Controller;

use App\TaxCalc\Application\Query\TaxSummaryResult;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dashboard')]
final class DashboardController extends AbstractController
{
    #[Route('', name: 'dashboard_index', methods: ['GET'])]
    public function index(): Response
    {
        $taxYear = 2025;

        // TODO: wire to real data via ports (GetTaxSummaryQuery → TaxSummaryResult)
        $summary = $this->getDemoSummary($taxYear);
        $this->addFlash('info', 'Tryb demo — wyświetlane są przykładowe dane.');

        return $this->render('dashboard/index.html.twig', [
            'summary' => $summary,
            'availableYears' => [2025, 2024, 2023],
        ]);
    }

    #[Route('/calculation/{taxYear}', name: 'dashboard_calculation', methods: ['GET'], requirements: [
        'taxYear' => '\d{4}',
    ])]
    public function calculation(int $taxYear): Response
    {
        // TODO: wire to real data via ports (GetTaxSummaryQuery → TaxSummaryResult)
        $summary = $this->getDemoSummary($taxYear);
        $this->addFlash('info', 'Tryb demo — wyświetlane są przykładowe dane.');

        return $this->render('dashboard/calculation.html.twig', [
            'summary' => $summary,
            'transactions' => [],
            'brokers' => [],
        ]);
    }

    #[Route('/fifo/{taxYear}', name: 'dashboard_fifo', methods: ['GET'], requirements: [
        'taxYear' => '\d{4}',
    ])]
    public function fifo(int $taxYear): Response
    {
        // TODO: wire to real data via ports (GetClosedPositionsQuery → FIFO data)
        $summary = $this->getDemoSummary($taxYear);
        $this->addFlash('info', 'Tryb demo — wyświetlane są przykładowe dane.');

        return $this->render('dashboard/fifo.html.twig', [
            'summary' => $summary,
            'instruments' => [],
        ]);
    }

    #[Route('/dividends/{taxYear}', name: 'dashboard_dividends', methods: ['GET'], requirements: [
        'taxYear' => '\d{4}',
    ])]
    public function dividends(int $taxYear): Response
    {
        // TODO: wire to real data via ports (GetDividendSummaryQuery)
        $summary = $this->getDemoSummary($taxYear);
        $this->addFlash('info', 'Tryb demo — wyświetlane są przykładowe dane.');

        return $this->render('dashboard/dividends.html.twig', [
            'summary' => $summary,
        ]);
    }

    /**
     * Placeholder summary with zeroed values for demo mode.
     * TODO: wire to real data via ports — remove this method when persistence is ready.
     */
    private function getDemoSummary(int $taxYear): TaxSummaryResult
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
