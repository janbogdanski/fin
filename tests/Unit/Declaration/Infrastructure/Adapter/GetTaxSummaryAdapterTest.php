<?php

declare(strict_types=1);

namespace App\Tests\Unit\Declaration\Infrastructure\Adapter;

use App\Declaration\Infrastructure\Adapter\GetTaxSummaryAdapter;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Port\ClosedPositionQueryPort;
use App\TaxCalc\Application\Port\DividendResultQueryPort;
use App\TaxCalc\Application\Port\PriorYearLossCrudPort;
use App\TaxCalc\Application\Port\PriorYearLossQueryPort;
use App\TaxCalc\Application\Query\GetTaxSummaryHandler;
use App\TaxCalc\Application\Query\TaxSummaryResult;
use App\TaxCalc\Application\Service\AnnualTaxCalculationService;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use PHPUnit\Framework\TestCase;

/**
 * Tests that GetTaxSummaryAdapter correctly delegates to GetTaxSummaryHandler
 * and returns the TaxSummaryResult.
 *
 * Both GetTaxSummaryHandler and AnnualTaxCalculationService are final readonly,
 * so we wire them with mocked ports instead of mocking the handler directly.
 */
final class GetTaxSummaryAdapterTest extends TestCase
{
    public function testDelegatesQueryToHandlerAndReturnsTaxSummaryResult(): void
    {
        $closedPositionQuery = $this->createMock(ClosedPositionQueryPort::class);
        $closedPositionQuery->method('findByUserYearAndCategory')->willReturn([]);

        $dividendResultQuery = $this->createMock(DividendResultQueryPort::class);
        $dividendResultQuery->method('findByUserAndYear')->willReturn([]);

        $priorYearLossQuery = $this->createMock(PriorYearLossQueryPort::class);
        $priorYearLossQuery->method('findByUserAndYear')->willReturn([]);

        $priorYearLossCrud = $this->createMock(PriorYearLossCrudPort::class);

        $calculationService = new AnnualTaxCalculationService(
            $closedPositionQuery,
            $dividendResultQuery,
            $priorYearLossQuery,
            $priorYearLossCrud,
        );

        $handler = new GetTaxSummaryHandler($calculationService);
        $adapter = new GetTaxSummaryAdapter($handler);

        $userId = UserId::fromString('019746a0-1234-7000-8000-000000000001');
        $taxYear = TaxYear::of(2025);

        $result = $adapter->getTaxSummary($userId, $taxYear);

        self::assertInstanceOf(TaxSummaryResult::class, $result);
        self::assertSame(2025, $result->taxYear);
    }

    public function testDelegatesCorrectTaxYearArgument(): void
    {
        $closedPositionQuery = $this->createMock(ClosedPositionQueryPort::class);
        $closedPositionQuery->method('findByUserYearAndCategory')->willReturn([]);
        $dividendResultQuery = $this->createMock(DividendResultQueryPort::class);
        $dividendResultQuery->method('findByUserAndYear')->willReturn([]);
        $priorYearLossQuery = $this->createMock(PriorYearLossQueryPort::class);
        $priorYearLossQuery->method('findByUserAndYear')->willReturn([]);

        $adapter = new GetTaxSummaryAdapter(new GetTaxSummaryHandler(
            new AnnualTaxCalculationService(
                $closedPositionQuery,
                $dividendResultQuery,
                $priorYearLossQuery,
                $this->createMock(PriorYearLossCrudPort::class),
            ),
        ));

        $userId = UserId::fromString('019746a0-1234-7000-8000-000000000002');

        self::assertSame(2023, $adapter->getTaxSummary($userId, TaxYear::of(2023))->taxYear);
        self::assertSame(2024, $adapter->getTaxSummary($userId, TaxYear::of(2024))->taxYear);
    }
}
