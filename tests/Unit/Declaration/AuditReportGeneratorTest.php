<?php

declare(strict_types=1);

namespace App\Tests\Unit\Declaration;

use App\Declaration\Domain\DTO\AuditReportData;
use App\Declaration\Domain\DTO\DividendEntry;
use App\Declaration\Domain\DTO\PriorYearLoss;
use App\Declaration\Domain\Service\AuditReportGenerator;
use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\NBPRate;
use App\Shared\Domain\ValueObject\TransactionId;
use App\TaxCalc\Domain\Model\ClosedPosition;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

final class AuditReportGeneratorTest extends TestCase
{
    private AuditReportGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new AuditReportGenerator();
    }

    public function testGeneratesHTML(): void
    {
        $html = $this->generator->generate($this->reportData());

        self::assertStringContainsString('<!DOCTYPE html>', $html);
        self::assertStringContainsString('</html>', $html);
        self::assertStringContainsString('Raport audytowy PIT-38', $html);
    }

    public function testContainsFIFOTable(): void
    {
        $html = $this->generator->generate($this->reportData());

        self::assertStringContainsString('Tabela FIFO matching', $html);
        self::assertStringContainsString('US0378331005', $html); // AAPL ISIN
        self::assertStringContainsString('2025-03-14', $html);   // buy date
        self::assertStringContainsString('2025-09-19', $html);   // sell date
        self::assertStringContainsString('79000.00', $html);     // proceeds
        self::assertStringContainsString('68850.00', $html);     // cost basis
    }

    public function testContainsDividendSection(): void
    {
        $html = $this->generator->generate($this->reportData());

        self::assertStringContainsString('Dywidendy per kraj', $html);
        self::assertStringContainsString('US', $html);
        self::assertStringContainsString('AAPL Dividend', $html);
        self::assertStringContainsString('1500.00', $html); // gross
        self::assertStringContainsString('225.00', $html);  // WHT
    }

    public function testContainsSummary(): void
    {
        $html = $this->generator->generate($this->reportData());

        self::assertStringContainsString('Podsumowanie koncowe', $html);
        self::assertStringContainsString('Podatek nalezny ogolem', $html);
        self::assertStringContainsString('1988', $html);
    }

    public function testContainsInstrumentSummary(): void
    {
        $html = $this->generator->generate($this->reportData());

        self::assertStringContainsString('Podsumowanie per instrument', $html);
    }

    public function testContainsBrokerSummary(): void
    {
        $html = $this->generator->generate($this->reportData());

        self::assertStringContainsString('Podsumowanie per broker', $html);
        self::assertStringContainsString('degiro', $html);
    }

    public function testContainsPriorYearLosses(): void
    {
        $html = $this->generator->generate($this->reportData());

        self::assertStringContainsString('Straty z lat poprzednich', $html);
        self::assertStringContainsString('2024', $html);
        self::assertStringContainsString('5000.00', $html);
    }

    public function testEmptyPositionsRendersMessage(): void
    {
        $data = new AuditReportData(
            taxYear: 2026,
            firstName: 'Jan',
            lastName: 'Kowalski',
            closedPositions: [],
            dividends: [],
            priorYearLosses: [],
            totalProceeds: '0',
            totalCosts: '0',
            totalGainLoss: '0',
            totalDividendGross: '0',
            totalDividendWHT: '0',
            totalTax: '0',
        );

        $html = $this->generator->generate($data);

        self::assertStringContainsString('Brak zamknietych pozycji', $html);
        self::assertStringContainsString('Brak dywidend', $html);
    }

    public function testEscapesHtmlEntities(): void
    {
        $data = new AuditReportData(
            taxYear: 2026,
            firstName: '<script>alert("xss")</script>',
            lastName: 'O\'Brien & Co.',
            closedPositions: [],
            dividends: [],
            priorYearLosses: [],
            totalProceeds: '0',
            totalCosts: '0',
            totalGainLoss: '0',
            totalDividendGross: '0',
            totalDividendWHT: '0',
            totalTax: '0',
        );

        $html = $this->generator->generate($data);

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    private function reportData(): AuditReportData
    {
        $closedPosition = new ClosedPosition(
            buyTransactionId: TransactionId::generate(),
            sellTransactionId: TransactionId::generate(),
            isin: ISIN::fromString('US0378331005'),
            quantity: BigDecimal::of('100'),
            costBasisPLN: BigDecimal::of('68850.00'),
            proceedsPLN: BigDecimal::of('79000.00'),
            buyCommissionPLN: BigDecimal::of('4.05'),
            sellCommissionPLN: BigDecimal::of('3.95'),
            gainLossPLN: BigDecimal::of('10142.00'),
            buyDate: new \DateTimeImmutable('2025-03-14'),
            sellDate: new \DateTimeImmutable('2025-09-19'),
            buyNBPRate: NBPRate::create(CurrencyCode::USD, BigDecimal::of('4.05'), new \DateTimeImmutable('2025-03-14'), '052/A/NBP/2025'),
            sellNBPRate: NBPRate::create(CurrencyCode::USD, BigDecimal::of('3.95'), new \DateTimeImmutable('2025-09-19'), '183/A/NBP/2025'),
            buyBroker: BrokerId::of('degiro'),
            sellBroker: BrokerId::of('degiro'),
        );

        $dividend = new DividendEntry(
            payDate: new \DateTimeImmutable('2025-06-15'),
            instrumentName: 'AAPL Dividend',
            countryCode: CountryCode::US,
            grossAmountPLN: '1500.00',
            whtPLN: '225.00',
            netAmountPLN: '1275.00',
            nbpRate: '4.05',
            nbpTableNumber: '115/A/NBP/2025',
        );

        $loss = new PriorYearLoss(
            year: 2024,
            amount: '5000.00',
            deducted: '2500.00',
        );

        return new AuditReportData(
            taxYear: 2026,
            firstName: 'Jan',
            lastName: 'Kowalski',
            closedPositions: [$closedPosition],
            dividends: [$dividend],
            priorYearLosses: [$loss],
            totalProceeds: '79000.00',
            totalCosts: '68854.05',
            totalGainLoss: '10145.95',
            totalDividendGross: '1500.00',
            totalDividendWHT: '225.00',
            totalTax: '1988',
        );
    }
}
