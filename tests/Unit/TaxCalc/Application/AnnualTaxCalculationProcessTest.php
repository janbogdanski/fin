<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Application;

use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Command\CalculateAnnualTax;
use App\TaxCalc\Application\Command\CalculateAnnualTaxHandler;
use App\TaxCalc\Application\Command\SavePriorYearLoss;
use App\TaxCalc\Application\Service\AnnualTaxCalculationService;
use App\TaxCalc\Domain\ValueObject\DividendTaxResult;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use App\Tests\Factory\ClosedPositionMother;
use App\Tests\Factory\NBPRateMother;
use App\Tests\InMemory\InMemoryClosedPositionQueryAdapter;
use App\Tests\InMemory\InMemoryDividendResultAdapter;
use App\Tests\InMemory\InMemoryPriorYearLossCrud;
use App\Tests\InMemory\InMemoryPriorYearLossQueryAdapter;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

final class AnnualTaxCalculationProcessTest extends TestCase
{
    private UserId $userId;

    private TaxYear $taxYear;

    private InMemoryClosedPositionQueryAdapter $closedPositions;

    private InMemoryDividendResultAdapter $dividends;

    private InMemoryPriorYearLossCrud $lossCrud;

    private CalculateAnnualTaxHandler $handler;

    protected function setUp(): void
    {
        $this->userId = UserId::generate();
        $this->taxYear = TaxYear::of(2025);
        $this->closedPositions = new InMemoryClosedPositionQueryAdapter();
        $this->dividends = new InMemoryDividendResultAdapter();
        $this->lossCrud = new InMemoryPriorYearLossCrud();

        $service = new AnnualTaxCalculationService(
            $this->closedPositions,
            $this->dividends,
            new InMemoryPriorYearLossQueryAdapter($this->lossCrud),
            $this->lossCrud,
        );

        $this->handler = new CalculateAnnualTaxHandler($service);
    }

    public function testCalculationCombinesEquityDividendAndPriorLossIntoFinalTax(): void
    {
        $this->closedPositions->seed(
            $this->userId,
            ClosedPositionMother::withGain('1000.00'),
            TaxCategory::EQUITY,
        );
        $this->dividends->saveAll($this->userId, $this->taxYear, [$this->usDividend('100.00', '15.00', '4.00')]);
        $this->lossCrud->save(new SavePriorYearLoss(
            userId: $this->userId,
            lossYear: 2023,
            taxCategory: TaxCategory::EQUITY,
            amount: BigDecimal::of('1200.00'),
        ));

        $result = ($this->handler)(new CalculateAnnualTax($this->userId, $this->taxYear));
        $lossRows = $this->lossCrud->findByUser($this->userId);

        self::assertTrue($result->isFinalized());
        self::assertTrue($result->equityGainLoss()->isEqualTo('1000.00'));
        self::assertTrue($result->equityLossDeduction()->isEqualTo('600.00'));
        self::assertTrue($result->equityTaxableIncome()->isEqualTo('400'));
        self::assertTrue($result->equityTax()->isEqualTo('76'));
        self::assertTrue($result->dividendTotalTaxDue()->isEqualTo('4.00'));
        self::assertTrue($result->totalTaxDue()->isEqualTo('80.00'));
        self::assertSame([2025], $lossRows[0]->usedInYears);
    }

    public function testCalculationDoesNotConsumePriorLossWhenCurrentYearHasNoPositiveGain(): void
    {
        $this->closedPositions->seed(
            $this->userId,
            ClosedPositionMother::withLoss('200.00'),
            TaxCategory::EQUITY,
        );
        $this->lossCrud->save(new SavePriorYearLoss(
            userId: $this->userId,
            lossYear: 2023,
            taxCategory: TaxCategory::EQUITY,
            amount: BigDecimal::of('1200.00'),
        ));

        $result = ($this->handler)(new CalculateAnnualTax($this->userId, $this->taxYear));
        $lossRows = $this->lossCrud->findByUser($this->userId);

        self::assertTrue($result->isFinalized());
        self::assertTrue($result->equityGainLoss()->isEqualTo('-200.00'));
        self::assertTrue($result->equityLossDeduction()->isZero());
        self::assertTrue($result->equityTaxableIncome()->isZero());
        self::assertTrue($result->equityTax()->isZero());
        self::assertSame([], $lossRows[0]->usedInYears);
    }

    private function usDividend(string $gross, string $wht, string $polishTaxDue): DividendTaxResult
    {
        return new DividendTaxResult(
            grossDividendPLN: \App\Shared\Domain\ValueObject\Money::of($gross, CurrencyCode::PLN),
            whtPaidPLN: \App\Shared\Domain\ValueObject\Money::of($wht, CurrencyCode::PLN),
            whtRate: BigDecimal::of('0.15'),
            upoRate: BigDecimal::of('0.15'),
            polishTaxDue: \App\Shared\Domain\ValueObject\Money::of($polishTaxDue, CurrencyCode::PLN),
            sourceCountry: CountryCode::US,
            nbpRate: NBPRateMother::usd405(),
        );
    }
}
