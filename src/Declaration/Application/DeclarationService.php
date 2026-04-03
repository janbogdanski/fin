<?php

declare(strict_types=1);

namespace App\Declaration\Application;

use App\Billing\Application\Port\PaymentRepositoryPort;
use App\Billing\Domain\Service\TierResolver;
use App\Billing\Domain\ValueObject\ProductCode;
use App\Billing\Domain\ValueObject\UserTier;
use App\BrokerImport\Application\Port\ImportedTransactionRepositoryInterface;
use App\Declaration\Application\Port\TaxSummaryQueryPort;
use App\Declaration\Application\Result\DeclarationResult;
use App\Declaration\Application\Result\NoData;
use App\Declaration\Application\Result\PaymentRequired;
use App\Declaration\Application\Result\PIT38WithSummary;
use App\Declaration\Application\Result\ProfileIncomplete;
use App\Declaration\Domain\DTO\PIT38Data;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Query\TaxSummaryResult;
use App\TaxCalc\Domain\ValueObject\TaxYear;

/**
 * Application service for the Declaration bounded context.
 *
 * Encapsulates:
 * - Building PIT38Data from TaxSummaryResult + user profile
 * - Checking profile completeness
 * - Value gate (tier resolution + payment verification)
 */
final readonly class DeclarationService
{
    public function __construct(
        private ImportedTransactionRepositoryInterface $importedTxRepo,
        private TaxSummaryQueryPort $taxSummaryQuery,
        private UserRepositoryInterface $userRepository,
        private TierResolver $tierResolver,
        private PaymentRepositoryPort $paymentRepository,
    ) {
    }

    /**
     * Build PIT-38 preview data (no payment gate, no profile completeness check).
     */
    public function buildPreview(UserId $userId, int $taxYear): DeclarationResult
    {
        if (! $this->hasTransactions($userId)) {
            return new NoData();
        }

        return $this->buildPIT38WithSummaryResult($userId, $taxYear);
    }

    /**
     * Build PIT-38 data for XML export (with payment gate + profile check).
     */
    public function buildPIT38ForExport(UserId $userId, int $taxYear): DeclarationResult
    {
        $gateResult = $this->checkValueGate($userId, $taxYear);

        if ($gateResult !== null) {
            return $gateResult;
        }

        if (! $this->hasTransactions($userId)) {
            return new NoData();
        }

        $result = $this->buildPIT38WithSummaryResult($userId, $taxYear);

        if ($result instanceof PIT38WithSummary && ! $result->pit38->hasCompletePersonalData()) {
            return new ProfileIncomplete();
        }

        return $result;
    }

    /**
     * Check whether the user's usage requires payment.
     *
     * @return PaymentRequired|null null means gate passed (free tier or paid)
     */
    public function checkValueGate(UserId $userId, int $taxYear): ?PaymentRequired
    {
        $brokerCount = $this->importedTxRepo->countBrokersByUser($userId);
        $closedPositionCount = $this->importedTxRepo->countSellsByUserAndYear($userId, $taxYear);

        $tier = $this->tierResolver->resolve($brokerCount, $closedPositionCount);

        if ($tier === UserTier::FREE) {
            return null;
        }

        $requiredProduct = $tier === UserTier::REQUIRES_PRO
            ? ProductCode::PRO
            : ProductCode::STANDARD;

        if ($this->paymentRepository->hasActivePaymentForTier($userId, $requiredProduct)) {
            return null;
        }

        return new PaymentRequired($requiredProduct);
    }

    public function hasTransactions(UserId $userId): bool
    {
        return $this->importedTxRepo->countByUser($userId) > 0;
    }

    /**
     * Resolve user profile data (name, NIP) for a given user.
     *
     * @return array{firstName: string, lastName: string}
     */
    public function resolveUserProfile(UserId $userId): array
    {
        $user = $this->userRepository->findById($userId);

        return [
            'firstName' => $user?->firstName() ?? '',
            'lastName' => $user?->lastName() ?? '',
        ];
    }

    private function buildPIT38WithSummaryResult(UserId $userId, int $taxYear): PIT38WithSummary
    {
        $summary = $this->taxSummaryQuery->getTaxSummary($userId, TaxYear::of($taxYear));

        $user = $this->userRepository->findById($userId);
        $nip = $user?->nip();
        $firstName = $user?->firstName();
        $lastName = $user?->lastName();

        $pit38 = $this->summaryToPIT38($summary, $nip, $firstName, $lastName);

        return new PIT38WithSummary($pit38, $summary);
    }

    private function summaryToPIT38(
        TaxSummaryResult $summary,
        ?string $nip,
        ?string $firstName,
        ?string $lastName,
    ): PIT38Data {
        $equityGainFloat = (float) $summary->equityGainLoss;
        $equityIncome = $equityGainFloat > 0 ? $summary->equityGainLoss : '0.00';
        $equityLoss = $equityGainFloat < 0 ? ltrim($summary->equityGainLoss, '-') : '0.00';

        $cryptoGainFloat = (float) $summary->cryptoGainLoss;
        $cryptoIncome = $cryptoGainFloat > 0 ? $summary->cryptoGainLoss : '0.00';
        $cryptoLoss = $cryptoGainFloat < 0 ? ltrim($summary->cryptoGainLoss, '-') : '0.00';

        $dividendGross = '0.00';
        $dividendWHT = '0.00';
        foreach ($summary->dividendsByCountry as $country) {
            $dividendGross = bcadd($dividendGross, $country->grossDividendPLN, 2);
            $dividendWHT = bcadd($dividendWHT, $country->whtPaidPLN, 2);
        }

        $equityCosts = bcadd($summary->equityCostBasis, $summary->equityCommissions, 2);
        $cryptoCosts = bcadd($summary->cryptoCostBasis, $summary->cryptoCommissions, 2);

        return new PIT38Data(
            taxYear: $summary->taxYear,
            nip: $nip,
            firstName: $firstName,
            lastName: $lastName,
            equityProceeds: $summary->equityProceeds,
            equityCosts: $equityCosts,
            equityIncome: $equityIncome,
            equityLoss: $equityLoss,
            equityTaxBase: $summary->equityTaxableIncome,
            equityTax: $summary->equityTax,
            dividendGross: $dividendGross,
            dividendWHT: $dividendWHT,
            dividendTaxDue: $summary->dividendTotalTaxDue,
            cryptoProceeds: $summary->cryptoProceeds,
            cryptoCosts: $cryptoCosts,
            cryptoIncome: $cryptoIncome,
            cryptoLoss: $cryptoLoss,
            cryptoTax: $summary->cryptoTax,
            totalTax: $summary->totalTaxDue,
            isCorrection: false,
        );
    }
}
