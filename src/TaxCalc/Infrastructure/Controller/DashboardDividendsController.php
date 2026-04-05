<?php

declare(strict_types=1);

namespace App\TaxCalc\Infrastructure\Controller;

use App\BrokerImport\Application\Port\ImportedTransactionRepositoryInterface;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Query\GetTaxSummary;
use App\TaxCalc\Application\Query\GetTaxSummaryHandler;
use App\TaxCalc\Application\Query\TaxSummaryResult;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardDividendsController extends AbstractController
{
    public function __construct(
        private readonly GetTaxSummaryHandler $taxSummaryHandler,
        private readonly ImportedTransactionRepositoryInterface $importedTxRepo,
    ) {
    }

    #[Route('/dashboard/dividends/{taxYear}', name: 'dashboard_dividends', methods: ['GET'], requirements: [
        'taxYear' => '\d{4}',
    ])]
    public function __invoke(int $taxYear): Response
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
