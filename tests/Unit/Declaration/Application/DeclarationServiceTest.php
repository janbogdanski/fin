<?php

declare(strict_types=1);

namespace App\Tests\Unit\Declaration\Application;

use App\Billing\Application\Port\PaymentRepositoryPort;
use App\Billing\Domain\Service\TierResolver;
use App\Billing\Domain\ValueObject\ProductCode;
use App\BrokerImport\Application\Port\ImportedTransactionRepositoryInterface;
use App\Declaration\Application\DeclarationService;
use App\Declaration\Application\Port\TaxSummaryQueryPort;
use App\Declaration\Application\Result\NoData;
use App\Declaration\Application\Result\PaymentRequired;
use App\Declaration\Application\Result\PIT38WithSummary;
use App\Declaration\Application\Result\ProfileIncomplete;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Query\TaxSummaryDividendCountry;
use App\TaxCalc\Application\Query\TaxSummaryResult;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DeclarationServiceTest extends TestCase
{
    private ImportedTransactionRepositoryInterface&MockObject $importedTxRepo;

    private TaxSummaryQueryPort&MockObject $taxSummaryQuery;

    private UserRepositoryInterface&MockObject $userRepository;

    private PaymentRepositoryPort&MockObject $paymentRepository;

    private DeclarationService $service;

    private UserId $userId;

    protected function setUp(): void
    {
        $this->importedTxRepo = $this->createMock(ImportedTransactionRepositoryInterface::class);
        $this->taxSummaryQuery = $this->createMock(TaxSummaryQueryPort::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->paymentRepository = $this->createMock(PaymentRepositoryPort::class);

        $this->service = new DeclarationService(
            $this->importedTxRepo,
            $this->taxSummaryQuery,
            $this->userRepository,
            new TierResolver(),
            $this->paymentRepository,
        );

        $this->userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
    }

    public function testBuildPreviewReturnsNoDataWhenNoTransactions(): void
    {
        $this->importedTxRepo->method('countByUser')->willReturn(0);

        $result = $this->service->buildPreview($this->userId, 2025);

        self::assertInstanceOf(NoData::class, $result);
    }

    public function testBuildPreviewReturnsPIT38WithSummaryWhenDataExists(): void
    {
        $this->importedTxRepo->method('countByUser')->willReturn(10);
        $this->stubTaxSummaryAndUser();

        $result = $this->service->buildPreview($this->userId, 2025);

        self::assertInstanceOf(PIT38WithSummary::class, $result);
        self::assertSame(2025, $result->pit38->taxYear);
        self::assertSame('1000.00', $result->pit38->equityProceeds);
    }

    public function testBuildPIT38ForExportReturnsPaymentRequiredWhenTierNotFree(): void
    {
        // 2 brokers + 50 sells -> REQUIRES_STANDARD
        $this->importedTxRepo->method('countBrokersByUser')->willReturn(2);
        $this->importedTxRepo->method('countSellsByUserAndYear')->willReturn(50);
        $this->paymentRepository->method('hasActivePaymentForTier')->willReturn(false);

        $result = $this->service->buildPIT38ForExport($this->userId, 2025);

        self::assertInstanceOf(PaymentRequired::class, $result);
        self::assertSame(ProductCode::STANDARD, $result->requiredProduct);
    }

    public function testBuildPIT38ForExportReturnsNoDataWhenNoTransactions(): void
    {
        $this->stubFreeGate();
        $this->importedTxRepo->method('countByUser')->willReturn(0);

        $result = $this->service->buildPIT38ForExport($this->userId, 2025);

        self::assertInstanceOf(NoData::class, $result);
    }

    public function testBuildPIT38ForExportReturnsProfileIncompleteWhenNipMissing(): void
    {
        $this->stubFreeGate();
        $this->importedTxRepo->method('countByUser')->willReturn(10);
        $this->stubTaxSummaryAndUser(nip: null);

        $result = $this->service->buildPIT38ForExport($this->userId, 2025);

        self::assertInstanceOf(ProfileIncomplete::class, $result);
    }

    public function testBuildPIT38ForExportReturnsPIT38WhenAllComplete(): void
    {
        $this->stubFreeGate();
        $this->importedTxRepo->method('countByUser')->willReturn(10);
        $this->stubTaxSummaryAndUser();

        $result = $this->service->buildPIT38ForExport($this->userId, 2025);

        self::assertInstanceOf(PIT38WithSummary::class, $result);
        self::assertTrue($result->pit38->hasCompletePersonalData());
    }

    public function testBuildPIT38ForExportPassesTierGateWithPayment(): void
    {
        // 2 brokers -> REQUIRES_STANDARD, but user has paid
        $this->importedTxRepo->method('countBrokersByUser')->willReturn(2);
        $this->importedTxRepo->method('countSellsByUserAndYear')->willReturn(50);
        $this->paymentRepository->method('hasActivePaymentForTier')->willReturn(true);
        $this->importedTxRepo->method('countByUser')->willReturn(10);
        $this->stubTaxSummaryAndUser();

        $result = $this->service->buildPIT38ForExport($this->userId, 2025);

        self::assertInstanceOf(PIT38WithSummary::class, $result);
    }

    public function testCheckValueGateReturnsNullForSmallUsage(): void
    {
        // 1 broker, 5 sells -> FREE
        $this->importedTxRepo->method('countBrokersByUser')->willReturn(1);
        $this->importedTxRepo->method('countSellsByUserAndYear')->willReturn(5);

        $result = $this->service->checkValueGate($this->userId, 2025);

        self::assertNull($result);
    }

    public function testCheckValueGateReturnsPaymentRequiredForNonFreeTier(): void
    {
        // 2 brokers -> REQUIRES_STANDARD
        $this->importedTxRepo->method('countBrokersByUser')->willReturn(2);
        $this->importedTxRepo->method('countSellsByUserAndYear')->willReturn(50);
        $this->paymentRepository->method('hasActivePaymentForTier')->willReturn(false);

        $result = $this->service->checkValueGate($this->userId, 2025);

        self::assertInstanceOf(PaymentRequired::class, $result);
        self::assertSame(ProductCode::STANDARD, $result->requiredProduct);
    }

    public function testSummaryToPIT38CalculatesEquityGainAsIncome(): void
    {
        $this->importedTxRepo->method('countByUser')->willReturn(10);

        $summary = $this->createSummary(equityGainLoss: '500.00', cryptoGainLoss: '-100.00');
        $this->taxSummaryQuery->method('getTaxSummary')->willReturn($summary);
        $this->stubUser();

        $result = $this->service->buildPreview($this->userId, 2025);

        self::assertInstanceOf(PIT38WithSummary::class, $result);
        self::assertSame('500.00', $result->pit38->equityIncome);
        self::assertSame('0.00', $result->pit38->equityLoss);
        self::assertSame('0.00', $result->pit38->cryptoIncome);
        self::assertSame('100.00', $result->pit38->cryptoLoss);
    }

    public function testSummaryToPIT38AggregatesDividendsByCountry(): void
    {
        $this->importedTxRepo->method('countByUser')->willReturn(10);

        $summary = $this->createSummary(dividends: [
            'US' => new TaxSummaryDividendCountry('US', '5000.00', '750.00', '200.00'),
            'DE' => new TaxSummaryDividendCountry('DE', '2000.00', '500.00', '80.00'),
        ]);
        $this->taxSummaryQuery->method('getTaxSummary')->willReturn($summary);
        $this->stubUser();

        $result = $this->service->buildPreview($this->userId, 2025);

        self::assertInstanceOf(PIT38WithSummary::class, $result);
        self::assertSame('7000.00', $result->pit38->dividendGross);
        self::assertSame('1250.00', $result->pit38->dividendWHT);
    }

    public function testSummaryToPIT38CalculatesCostsAsCostBasisPlusCommissions(): void
    {
        $this->importedTxRepo->method('countByUser')->willReturn(10);

        $summary = $this->createSummary();
        $this->taxSummaryQuery->method('getTaxSummary')->willReturn($summary);
        $this->stubUser();

        $result = $this->service->buildPreview($this->userId, 2025);

        self::assertInstanceOf(PIT38WithSummary::class, $result);
        // equityCosts = equityCostBasis(800) + equityCommissions(20) = 820
        self::assertSame('820.00', $result->pit38->equityCosts);
        // cryptoCosts = cryptoCostBasis(400) + cryptoCommissions(10) = 410
        self::assertSame('410.00', $result->pit38->cryptoCosts);
    }

    public function testHasTransactionsReturnsTrueWhenCountPositive(): void
    {
        $this->importedTxRepo->method('countByUser')->willReturn(5);

        self::assertTrue($this->service->hasTransactions($this->userId));
    }

    public function testHasTransactionsReturnsFalseWhenCountZero(): void
    {
        $this->importedTxRepo->method('countByUser')->willReturn(0);

        self::assertFalse($this->service->hasTransactions($this->userId));
    }

    public function testResolveUserProfileReturnsNameFromUser(): void
    {
        $this->stubUser();

        $profile = $this->service->resolveUserProfile($this->userId);

        self::assertSame('Jan', $profile->firstName);
        self::assertSame('Kowalski', $profile->lastName);
    }

    public function testResolveUserProfileReturnsEmptyStringsWhenUserNotFound(): void
    {
        $this->userRepository->method('findById')->willReturn(null);

        $profile = $this->service->resolveUserProfile($this->userId);

        self::assertSame('', $profile->firstName);
        self::assertSame('', $profile->lastName);
    }

    private function stubFreeGate(): void
    {
        // 1 broker, 5 sells -> FREE tier (no payment needed)
        $this->importedTxRepo->method('countBrokersByUser')->willReturn(1);
        $this->importedTxRepo->method('countSellsByUserAndYear')->willReturn(5);
    }

    private function stubTaxSummaryAndUser(?string $nip = '5260000005'): void
    {
        $summary = $this->createSummary();
        $this->taxSummaryQuery->method('getTaxSummary')->willReturn($summary);
        $this->stubUser($nip);
    }

    private function stubUser(?string $nip = '5260000005'): void
    {
        $user = User::register(
            $this->userId,
            'jan@example.com',
            new \DateTimeImmutable(),
        );

        if ($nip !== null) {
            $user->updateProfile($nip, null, 'Jan', 'Kowalski');
        }

        $this->userRepository->method('findById')->willReturn($user);
    }

    /**
     * @param array<string, TaxSummaryDividendCountry> $dividends
     */
    private function createSummary(
        string $equityGainLoss = '0.00',
        string $cryptoGainLoss = '0.00',
        array $dividends = [],
    ): TaxSummaryResult {
        return new TaxSummaryResult(
            taxYear: 2025,
            equityProceeds: '1000.00',
            equityCostBasis: '800.00',
            equityCommissions: '20.00',
            equityGainLoss: $equityGainLoss,
            equityLossDeduction: '0.00',
            equityTaxableIncome: '0.00',
            equityTax: '0.00',
            dividendsByCountry: $dividends,
            dividendTotalTaxDue: '0.00',
            cryptoProceeds: '500.00',
            cryptoCostBasis: '400.00',
            cryptoCommissions: '10.00',
            cryptoGainLoss: $cryptoGainLoss,
            cryptoLossDeduction: '0.00',
            cryptoTaxableIncome: '0.00',
            cryptoTax: '0.00',
            totalTaxDue: '0.00',
        );
    }
}
