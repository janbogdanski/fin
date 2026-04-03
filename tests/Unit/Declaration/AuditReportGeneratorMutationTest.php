<?php

declare(strict_types=1);

namespace App\Tests\Unit\Declaration;

use App\Declaration\Domain\DTO\AuditReportData;
use App\Declaration\Domain\DTO\ClosedPositionEntry;
use App\Declaration\Domain\DTO\DividendEntry;
use App\Declaration\Domain\DTO\PriorYearLossEntry;
use App\Declaration\Domain\Service\AuditReportGenerator;
use App\Shared\Domain\ValueObject\CountryCode;
use PHPUnit\Framework\TestCase;

/**
 * Mutation-killing tests for AuditReportGenerator.
 * Targets: column order, gain/loss CSS class, grouped summary, dividend totals, empty states.
 */
final class AuditReportGeneratorMutationTest extends TestCase
{
    private AuditReportGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new AuditReportGenerator();
    }

    /**
     * Kills Concat/ConcatOperandRemoval mutants on FIFO row (line 88).
     * Assert the FULL row string to kill any operand removal or swap.
     */
    public function testFIFOTableRowIsExactlyComplete(): void
    {
        $html = $this->generator->generate($this->reportData(), new \DateTimeImmutable('2025-06-15 12:00:00'));

        // The entire FIFO row must be present as one continuous string
        $expectedRow = '<tr>'
            . '<td class="left">US0378331005</td>'
            . '<td>2025-03-14</td>'
            . '<td>2025-09-19</td>'
            . '<td>100</td>'
            . '<td>68850.00</td>'
            . '<td>79000.00</td>'
            . '<td>4.05</td>'
            . '<td>3.95</td>'
            . '<td>4.05</td>'
            . '<td>3.95</td>'
            . '<td class="gain">10142.00</td>'
            . '</tr>';

        self::assertStringContainsString($expectedRow, $html);
    }

    /**
     * Kills CSS class mutants: positive gain gets 'gain' class, negative loss gets 'loss' class.
     */
    public function testGainCSSClassForPositiveGainLoss(): void
    {
        $html = $this->generator->generate($this->reportData(), new \DateTimeImmutable('2025-06-15 12:00:00'));

        // Positive gain (10142.00) should have class="gain"
        self::assertStringContainsString('class="gain">10142.00', $html);
    }

    /**
     * Verifies loss CSS class is applied for negative gain/loss.
     */
    public function testLossCSSClassForNegativeGainLoss(): void
    {
        $lossPosition = new ClosedPositionEntry(
            isin: 'US0378331005',
            buyDate: '2025-03-14',
            sellDate: '2025-09-19',
            quantity: '100',
            costBasisPLN: '79000.00',
            proceedsPLN: '68850.00',
            buyCommissionPLN: '4.05',
            sellCommissionPLN: '3.95',
            gainLossPLN: '-10158.00',
            buyNBPRate: '3.95',
            sellNBPRate: '4.05',
            sellBroker: 'degiro',
        );

        $data = new AuditReportData(
            taxYear: 2026,
            firstName: 'Jan',
            lastName: 'Kowalski',
            closedPositions: [$lossPosition],
            dividends: [],
            priorYearLosses: [],
            totalProceeds: '68850.00',
            totalCosts: '79000.00',
            totalGainLoss: '-10158.00',
            totalDividendGross: '0',
            totalDividendWHT: '0',
            totalTax: '0',
        );

        $html = $this->generator->generate($data, new \DateTimeImmutable('2025-06-15 12:00:00'));

        self::assertStringContainsString('class="loss">-10158.00', $html);
    }

    /**
     * Kills Concat/ConcatOperandRemoval mutants on grouped summary row (line 193).
     * Assert the full instrument summary row.
     */
    public function testInstrumentSummaryGroupedRowIsComplete(): void
    {
        $html = $this->generator->generate($this->reportData(), new \DateTimeImmutable('2025-06-15 12:00:00'));

        self::assertStringContainsString('Podsumowanie per instrument', $html);

        // The full instrument summary row
        $expectedRow = '<tr>'
            . '<td class="left">US0378331005</td>'
            . '<td>79000.00</td>'
            . '<td>68850.00</td>'
            . '<td class="gain">10142.00</td>'
            . '</tr>';

        self::assertStringContainsString($expectedRow, $html);
    }

    /**
     * Kills Concat/ConcatOperandRemoval mutants on broker summary row.
     * Assert the full broker summary row.
     */
    public function testBrokerSummaryGroupedRowIsComplete(): void
    {
        $html = $this->generator->generate($this->reportData(), new \DateTimeImmutable('2025-06-15 12:00:00'));

        self::assertStringContainsString('Podsumowanie per broker', $html);

        $expectedRow = '<tr>'
            . '<td class="left">degiro</td>'
            . '<td>79000.00</td>'
            . '<td>68850.00</td>'
            . '<td class="gain">10142.00</td>'
            . '</tr>';

        self::assertStringContainsString($expectedRow, $html);
    }

    /**
     * Kills Concat/ConcatOperandRemoval mutants on dividend total row (line 256).
     * Assert the full total row.
     */
    public function testDividendSectionCountryTotalRowIsComplete(): void
    {
        $html = $this->generator->generate($this->reportData(), new \DateTimeImmutable('2025-06-15 12:00:00'));

        $expectedRow = '<tr class="summary-row">'
            . '<td colspan="2" class="left">Suma US</td>'
            . '<td>1500.00</td>'
            . '<td>225.00</td>'
            . '<td>1275.00</td>'
            . '<td colspan="2"></td>'
            . '</tr>';

        self::assertStringContainsString($expectedRow, $html);
    }

    /**
     * Kills mutants on table headers: verify FIFO table has all required column headers.
     */
    public function testFIFOTableHeaders(): void
    {
        $html = $this->generator->generate($this->reportData(), new \DateTimeImmutable('2025-06-15 12:00:00'));

        $requiredHeaders = [
            'ISIN',
            'Data kupna',
            'Data sprzedazy',
            'Ilosc',
            'Koszt (PLN)',
            'Przychod (PLN)',
            'Kurs NBP kupno',
            'Kurs NBP sprzedaz',
            'Prowizja kupno (PLN)',
            'Prowizja sprzedaz (PLN)',
            'Zysk/Strata (PLN)',
        ];

        foreach ($requiredHeaders as $header) {
            self::assertStringContainsString("<th>{$header}</th>", $html);
        }
    }

    /**
     * Kills mutants on dividend table headers.
     */
    public function testDividendTableHeaders(): void
    {
        $html = $this->generator->generate($this->reportData(), new \DateTimeImmutable('2025-06-15 12:00:00'));

        $requiredHeaders = [
            'Data',
            'Instrument',
            'Brutto (PLN)',
            'WHT (PLN)',
            'Netto (PLN)',
            'Kurs NBP',
            'Tabela NBP',
        ];

        foreach ($requiredHeaders as $header) {
            self::assertStringContainsString("<th>{$header}</th>", $html);
        }
    }

    /**
     * Kills Concat/ConcatOperandRemoval mutants on prior year loss row (line 281).
     * Assert the full row.
     */
    public function testPriorYearLossRowIsComplete(): void
    {
        $html = $this->generator->generate($this->reportData(), new \DateTimeImmutable('2025-06-15 12:00:00'));

        self::assertStringContainsString('Straty z lat poprzednich', $html);
        self::assertStringContainsString('<th>Rok</th>', $html);
        self::assertStringContainsString('<th>Kwota straty (PLN)</th>', $html);
        self::assertStringContainsString('<th>Odliczone (PLN)</th>', $html);

        $expectedRow = '<tr>'
            . '<td>2024</td>'
            . '<td>5000.00</td>'
            . '<td>2500.00</td>'
            . '</tr>';

        self::assertStringContainsString($expectedRow, $html);
    }

    /**
     * Kills mutants on total summary section: every line item must appear.
     */
    public function testTotalSummaryContainsAllLineItems(): void
    {
        $html = $this->generator->generate($this->reportData(), new \DateTimeImmutable('2025-06-15 12:00:00'));

        $lineItems = [
            'Przychod z odplatnego zbycia' => '79000.00',
            'Koszty uzyskania przychodu' => '68854.05',
            'Dochod/Strata z odplatnego zbycia' => '10145.95',
            'Dywidendy brutto' => '1500.00',
            'WHT zaplacony za granica' => '225.00',
            'Podatek nalezny ogolem' => '1988',
        ];

        foreach ($lineItems as $label => $value) {
            self::assertStringContainsString($label, $html);
            self::assertStringContainsString($value, $html);
        }
    }

    /**
     * Kills toScale IncrementInteger mutants (lines 195-197, 258-260).
     * Grouped summary and dividend total use toScale(2). With toScale(3), extra decimal appears.
     * Use input that would show differently at scale 3 vs scale 2.
     */
    public function testGroupedSummaryUsesScale2NotScale3(): void
    {
        // Use a position with values that are exact at scale 2
        $html = $this->generator->generate($this->reportData(), new \DateTimeImmutable('2025-06-15 12:00:00'));

        // Values at scale 2: "79000.00", not "79000.000"
        self::assertStringNotContainsString('79000.000', $html);
        self::assertStringNotContainsString('68850.000', $html);
        self::assertStringNotContainsString('10142.000', $html);
    }

    /**
     * Kills mutants on the footer content.
     */
    public function testFooterContent(): void
    {
        $html = $this->generator->generate($this->reportData(), new \DateTimeImmutable('2025-06-15 12:00:00'));

        self::assertStringContainsString('wygenerowany automatycznie przez TaxPilot', $html);
        self::assertStringContainsString('nie zastepuje profesjonalnej porady podatkowej', $html);
    }

    /**
     * Kills Concat/ConcatOperandRemoval mutants on dividend entry row (line 241).
     * Assert the full dividend entry row.
     */
    public function testDividendEntryRowIsComplete(): void
    {
        $html = $this->generator->generate($this->reportData(), new \DateTimeImmutable('2025-06-15 12:00:00'));

        $expectedRow = '<tr>'
            . '<td>2025-06-15</td>'
            . '<td class="left">AAPL Dividend</td>'
            . '<td>1500.00</td>'
            . '<td>225.00</td>'
            . '<td>1275.00</td>'
            . '<td>4.05</td>'
            . '<td>115/A/NBP/2025</td>'
            . '</tr>';

        self::assertStringContainsString($expectedRow, $html);
    }

    /**
     * Kills mutants on empty grouped summary: no instrument/broker summary when no positions.
     */
    public function testNoGroupedSummaryWhenNoPositions(): void
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

        $html = $this->generator->generate($data, new \DateTimeImmutable('2025-06-15 12:00:00'));

        // When no positions, instrument/broker summaries should NOT appear
        self::assertStringNotContainsString('Podsumowanie per instrument', $html);
        self::assertStringNotContainsString('Podsumowanie per broker', $html);
        // But the FIFO empty message should appear
        self::assertStringContainsString('Brak zamknietych pozycji', $html);
    }

    /**
     * Kills mutants on empty prior year losses: no section rendered.
     */
    public function testNoPriorYearLossSectionWhenEmpty(): void
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

        $html = $this->generator->generate($data, new \DateTimeImmutable('2025-06-15 12:00:00'));

        self::assertStringNotContainsString('Straty z lat poprzednich', $html);
    }

    /**
     * Kills mutants on taxpayer name rendering.
     */
    public function testTaxpayerNameRendered(): void
    {
        $html = $this->generator->generate($this->reportData(), new \DateTimeImmutable('2025-06-15 12:00:00'));

        self::assertStringContainsString('Podatnik: Jan Kowalski', $html);
    }

    private function reportData(): AuditReportData
    {
        $closedPosition = new ClosedPositionEntry(
            isin: 'US0378331005',
            buyDate: '2025-03-14',
            sellDate: '2025-09-19',
            quantity: '100',
            costBasisPLN: '68850.00',
            proceedsPLN: '79000.00',
            buyCommissionPLN: '4.05',
            sellCommissionPLN: '3.95',
            gainLossPLN: '10142.00',
            buyNBPRate: '4.05',
            sellNBPRate: '3.95',
            sellBroker: 'degiro',
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

        $loss = new PriorYearLossEntry(
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
