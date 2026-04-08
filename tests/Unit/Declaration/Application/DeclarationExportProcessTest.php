<?php

declare(strict_types=1);

namespace App\Tests\Unit\Declaration\Application;

use App\Billing\Domain\Service\TierResolver;
use App\Billing\Domain\ValueObject\ProductCode;
use App\Declaration\Application\DeclarationService;
use App\Declaration\Application\Result\PaymentRequired;
use App\Declaration\Application\Result\PIT38WithSummary;
use App\Declaration\Application\Result\ProfileIncomplete;
use App\Declaration\Infrastructure\Adapter\GetTaxSummaryAdapter;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Query\GetTaxSummaryHandler;
use App\TaxCalc\Application\Service\AnnualTaxCalculationService;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\Tests\Factory\ClosedPositionMother;
use App\Tests\Factory\UserMother;
use App\Tests\InMemory\InMemoryClosedPositionQueryAdapter;
use App\Tests\InMemory\InMemoryDividendResultAdapter;
use App\Tests\InMemory\InMemoryImportedTransactionRepository;
use App\Tests\InMemory\InMemoryPaymentRepository;
use App\Tests\InMemory\InMemoryPriorYearLossCrud;
use App\Tests\InMemory\InMemoryPriorYearLossQueryAdapter;
use App\Tests\InMemory\InMemoryUserRepository;
use PHPUnit\Framework\TestCase;

final class DeclarationExportProcessTest extends TestCase
{
    private UserId $userId;

    private InMemoryImportedTransactionRepository $importedTransactions;

    private InMemoryUserRepository $users;

    private InMemoryPaymentRepository $payments;

    private InMemoryClosedPositionQueryAdapter $closedPositions;

    private DeclarationService $service;

    protected function setUp(): void
    {
        $this->userId = UserId::generate();
        $this->importedTransactions = new InMemoryImportedTransactionRepository();
        $this->users = new InMemoryUserRepository();
        $this->payments = new InMemoryPaymentRepository();
        $this->closedPositions = new InMemoryClosedPositionQueryAdapter();
        $lossCrud = new InMemoryPriorYearLossCrud();

        $calculationService = new AnnualTaxCalculationService(
            $this->closedPositions,
            new InMemoryDividendResultAdapter(),
            new InMemoryPriorYearLossQueryAdapter($lossCrud),
            $lossCrud,
        );

        $this->service = new DeclarationService(
            $this->importedTransactions,
            new GetTaxSummaryAdapter(new GetTaxSummaryHandler($calculationService)),
            $this->users,
            new TierResolver(),
            $this->payments,
        );
    }

    public function testExportReturnsPaymentRequiredBeforeReadingTaxSummaryWhenUsageExceedsFreeTier(): void
    {
        $this->users->save(UserMother::withProfile(id: $this->userId));
        $this->importedTransactions->seedStats(
            $this->userId,
            total: 50,
            brokers: 2,
            sellsByYear: [
                2025 => 50,
            ],
        );

        $result = $this->service->buildPIT38ForExport($this->userId, 2025);

        self::assertInstanceOf(PaymentRequired::class, $result);
        self::assertSame(ProductCode::STANDARD, $result->requiredProduct);
    }

    public function testExportReturnsProfileIncompleteWhenGatePassesButUserProfileIsMissing(): void
    {
        $this->users->save(UserMother::withoutNIP($this->userId));
        $this->importedTransactions->seedStats(
            $this->userId,
            total: 1,
            brokers: 1,
            sellsByYear: [
                2025 => 1,
            ],
        );
        $this->closedPositions->seed(
            $this->userId,
            ClosedPositionMother::withGain('1000.00'),
            TaxCategory::EQUITY,
        );

        $result = $this->service->buildPIT38ForExport($this->userId, 2025);

        self::assertInstanceOf(ProfileIncomplete::class, $result);
    }

    public function testExportReturnsPit38WhenGatePassesAndProfileIsComplete(): void
    {
        $this->users->save(UserMother::withProfile(id: $this->userId));
        $this->importedTransactions->seedStats(
            $this->userId,
            total: 1,
            brokers: 1,
            sellsByYear: [
                2025 => 1,
            ],
        );
        $this->closedPositions->seed(
            $this->userId,
            ClosedPositionMother::withGain('1000.00'),
            TaxCategory::EQUITY,
        );

        $result = $this->service->buildPIT38ForExport($this->userId, 2025);

        self::assertInstanceOf(PIT38WithSummary::class, $result);
        self::assertSame('1000.00', $result->pit38->equityIncome);
        self::assertSame('190', $result->pit38->equityTax);
        self::assertTrue($result->pit38->hasCompletePersonalData());
    }
}
