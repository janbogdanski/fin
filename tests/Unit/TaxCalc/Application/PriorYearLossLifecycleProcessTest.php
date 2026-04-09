<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Application;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Command\CalculateAnnualTax;
use App\TaxCalc\Application\Command\CalculateAnnualTaxHandler;
use App\TaxCalc\Application\Command\SavePriorYearLoss;
use App\TaxCalc\Application\Service\AnnualTaxCalculationService;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use App\Tests\Factory\ClosedPositionMother;
use App\Tests\InMemory\InMemoryClosedPositionQueryAdapter;
use App\Tests\InMemory\InMemoryDividendResultAdapter;
use App\Tests\InMemory\InMemoryPriorYearLossCrud;
use App\Tests\InMemory\InMemoryPriorYearLossQueryAdapter;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class PriorYearLossLifecycleProcessTest extends TestCase
{
    private UserId $userId;

    private TaxYear $taxYear;

    private InMemoryClosedPositionQueryAdapter $closedPositions;

    private InMemoryPriorYearLossCrud $lossCrud;

    private InMemoryPriorYearLossQueryAdapter $lossQuery;

    private CalculateAnnualTaxHandler $calculateAnnualTax;

    protected function setUp(): void
    {
        $this->userId = UserId::generate();
        $this->taxYear = TaxYear::of(2025);
        $this->closedPositions = new InMemoryClosedPositionQueryAdapter();
        $this->lossCrud = new InMemoryPriorYearLossCrud(new MockClock(new \DateTimeImmutable('2026-04-09 10:00:00')));
        $this->lossQuery = new InMemoryPriorYearLossQueryAdapter($this->lossCrud);

        $service = new AnnualTaxCalculationService(
            $this->closedPositions,
            new InMemoryDividendResultAdapter(),
            $this->lossQuery,
            $this->lossCrud,
        );

        $this->calculateAnnualTax = new CalculateAnnualTaxHandler($service);
    }

    public function testLossCanBeSavedThenGetsLockedAfterBeingUsedInCalculation(): void
    {
        $this->lossCrud->save(new SavePriorYearLoss(
            userId: $this->userId,
            lossYear: 2023,
            taxCategory: TaxCategory::EQUITY,
            amount: BigDecimal::of('1200.00'),
        ));
        $this->lossCrud->save(new SavePriorYearLoss(
            userId: $this->userId,
            lossYear: 2023,
            taxCategory: TaxCategory::EQUITY,
            amount: BigDecimal::of('1500.00'),
        ));
        $this->closedPositions->seed(
            $this->userId,
            ClosedPositionMother::withGain('1000.00'),
            TaxCategory::EQUITY,
        );

        $rangesBeforeCalculation = $this->lossQuery->findByUserAndYear($this->userId, $this->taxYear);
        $result = ($this->calculateAnnualTax)(new CalculateAnnualTax($this->userId, $this->taxYear));
        $rowsAfterCalculation = $this->lossCrud->findByUser($this->userId);

        self::assertCount(1, $rangesBeforeCalculation);
        self::assertSame('750.00', $rangesBeforeCalculation[0]->maxDeductionThisYear->__toString());
        self::assertTrue($result->equityLossDeduction()->isEqualTo('750.00'));
        self::assertSame([2025], $rowsAfterCalculation[0]->usedInYears);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot reduce original amount of loss');

        $this->lossCrud->save(new SavePriorYearLoss(
            userId: $this->userId,
            lossYear: 2023,
            taxCategory: TaxCategory::EQUITY,
            amount: BigDecimal::of('1000.00'),
        ));
    }

    public function testUsedLossCannotBeDeletedAfterCalculationLocksIt(): void
    {
        $this->lossCrud->save(new SavePriorYearLoss(
            userId: $this->userId,
            lossYear: 2023,
            taxCategory: TaxCategory::EQUITY,
            amount: BigDecimal::of('1200.00'),
        ));
        $this->closedPositions->seed(
            $this->userId,
            ClosedPositionMother::withGain('1000.00'),
            TaxCategory::EQUITY,
        );

        ($this->calculateAnnualTax)(new CalculateAnnualTax($this->userId, $this->taxYear));

        $row = $this->lossCrud->findByUser($this->userId)[0];

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot delete loss');

        $this->lossCrud->delete($row->id, $this->userId);
    }
}
