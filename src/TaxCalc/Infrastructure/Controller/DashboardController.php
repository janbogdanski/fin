<?php

declare(strict_types=1);

namespace App\TaxCalc\Infrastructure\Controller;

use App\TaxCalc\Application\Query\TaxSummaryDividendCountry;
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
        $summary = $this->getMockSummary($taxYear);

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
        $summary = $this->getMockSummary($taxYear);

        return $this->render('dashboard/calculation.html.twig', [
            'summary' => $summary,
            'transactions' => $this->getMockTransactions(),
            'brokers' => ['IBKR', 'Degiro'],
        ]);
    }

    #[Route('/fifo/{taxYear}', name: 'dashboard_fifo', methods: ['GET'], requirements: [
        'taxYear' => '\d{4}',
    ])]
    public function fifo(int $taxYear): Response
    {
        $summary = $this->getMockSummary($taxYear);

        return $this->render('dashboard/fifo.html.twig', [
            'summary' => $summary,
            'instruments' => $this->getMockFifoData(),
        ]);
    }

    #[Route('/dividends/{taxYear}', name: 'dashboard_dividends', methods: ['GET'], requirements: [
        'taxYear' => '\d{4}',
    ])]
    public function dividends(int $taxYear): Response
    {
        $summary = $this->getMockSummary($taxYear);

        return $this->render('dashboard/dividends.html.twig', [
            'summary' => $summary,
        ]);
    }

    /**
     * Mock data — will be replaced by Query bus (GetTaxSummary) once persistence is ready.
     */
    private function getMockSummary(int $taxYear): TaxSummaryResult
    {
        return new TaxSummaryResult(
            taxYear: $taxYear,
            equityProceeds: '125430.50',
            equityCostBasis: '98200.00',
            equityCommissions: '1230.45',
            equityGainLoss: '26000.05',
            equityLossDeduction: '0.00',
            equityTaxableIncome: '26000.05',
            equityTax: '4940.01',
            dividendsByCountry: [
                'US' => new TaxSummaryDividendCountry(
                    countryCode: 'US',
                    grossDividendPLN: '8500.00',
                    whtPaidPLN: '1275.00',
                    polishTaxDue: '340.00',
                ),
                'DE' => new TaxSummaryDividendCountry(
                    countryCode: 'DE',
                    grossDividendPLN: '3200.00',
                    whtPaidPLN: '832.00',
                    polishTaxDue: '0.00',
                ),
                'IE' => new TaxSummaryDividendCountry(
                    countryCode: 'IE',
                    grossDividendPLN: '1450.00',
                    whtPaidPLN: '290.00',
                    polishTaxDue: '0.00',
                ),
            ],
            dividendTotalTaxDue: '340.00',
            cryptoProceeds: '45000.00',
            cryptoCostBasis: '38000.00',
            cryptoCommissions: '450.00',
            cryptoGainLoss: '6550.00',
            cryptoLossDeduction: '0.00',
            cryptoTaxableIncome: '6550.00',
            cryptoTax: '1244.50',
            totalTaxDue: '6524.51',
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getMockTransactions(): array
    {
        return [
            [
                'date' => '2025-01-15',
                'instrument' => 'AAPL',
                'type' => 'BUY',
                'quantity' => '10',
                'priceOriginal' => '185.50',
                'currency' => 'USD',
                'nbpRate' => '4.0523',
                'gainLossPLN' => null,
                'broker' => 'IBKR',
            ],
            [
                'date' => '2025-03-22',
                'instrument' => 'AAPL',
                'type' => 'SELL',
                'quantity' => '10',
                'priceOriginal' => '198.30',
                'currency' => 'USD',
                'nbpRate' => '4.0812',
                'gainLossPLN' => '4920.15',
                'broker' => 'IBKR',
            ],
            [
                'date' => '2025-02-10',
                'instrument' => 'VWCE.DE',
                'type' => 'BUY',
                'quantity' => '50',
                'priceOriginal' => '108.20',
                'currency' => 'EUR',
                'nbpRate' => '4.3215',
                'gainLossPLN' => null,
                'broker' => 'Degiro',
            ],
            [
                'date' => '2025-06-18',
                'instrument' => 'VWCE.DE',
                'type' => 'SELL',
                'quantity' => '50',
                'priceOriginal' => '115.80',
                'currency' => 'EUR',
                'nbpRate' => '4.2980',
                'gainLossPLN' => '15630.20',
                'broker' => 'Degiro',
            ],
            [
                'date' => '2025-03-15',
                'instrument' => 'AAPL',
                'type' => 'DIVIDEND',
                'quantity' => '10',
                'priceOriginal' => '0.96',
                'currency' => 'USD',
                'nbpRate' => '4.0650',
                'gainLossPLN' => '39.02',
                'broker' => 'IBKR',
            ],
            [
                'date' => '2025-04-20',
                'instrument' => 'BTC',
                'type' => 'BUY',
                'quantity' => '0.5',
                'priceOriginal' => '62000.00',
                'currency' => 'USD',
                'nbpRate' => '4.0320',
                'gainLossPLN' => null,
                'broker' => 'IBKR',
            ],
            [
                'date' => '2025-09-10',
                'instrument' => 'BTC',
                'type' => 'SELL',
                'quantity' => '0.5',
                'priceOriginal' => '75000.00',
                'currency' => 'USD',
                'nbpRate' => '4.0150',
                'gainLossPLN' => '6550.00',
                'broker' => 'IBKR',
            ],
        ];
    }

    /**
     * @return array<string, array<int, array<string, string|null>>>
     */
    private function getMockFifoData(): array
    {
        return [
            'AAPL' => [
                [
                    'buyDate' => '2025-01-15',
                    'buyPrice' => '185.50 USD',
                    'buyNbpRate' => '4.0523',
                    'sellDate' => '2025-03-22',
                    'sellPrice' => '198.30 USD',
                    'sellNbpRate' => '4.0812',
                    'quantity' => '10',
                    'costBasisPLN' => '7516.97',
                    'proceedsPLN' => '8093.02',
                    'gainLossPLN' => '576.05',
                ],
            ],
            'VWCE.DE' => [
                [
                    'buyDate' => '2025-02-10',
                    'buyPrice' => '108.20 EUR',
                    'buyNbpRate' => '4.3215',
                    'sellDate' => '2025-06-18',
                    'sellPrice' => '115.80 EUR',
                    'sellNbpRate' => '4.2980',
                    'quantity' => '50',
                    'costBasisPLN' => '23379.33',
                    'proceedsPLN' => '24895.62',
                    'gainLossPLN' => '1516.29',
                ],
            ],
            'BTC' => [
                [
                    'buyDate' => '2025-04-20',
                    'buyPrice' => '62000.00 USD',
                    'buyNbpRate' => '4.0320',
                    'sellDate' => '2025-09-10',
                    'sellPrice' => '75000.00 USD',
                    'sellNbpRate' => '4.0150',
                    'quantity' => '0.5',
                    'costBasisPLN' => '124992.00',
                    'proceedsPLN' => '150562.50',
                    'gainLossPLN' => '25570.50',
                ],
            ],
        ];
    }
}
