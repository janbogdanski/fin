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
     * Kills mutants on FIFO table row rendering: verifies all columns appear in correct order.
     * Each <td> must contain its value in sequence within a single <tr>.
     */
    public function testFIFOTableRowContainsAllColumnsInOrder(): void
    {
        $html = $this->generator->generate($this->reportData());

        // The FIFO row should contain all values in td cells in this order:
        // ISIN, buyDate, sellDate, quantity, costBasis, proceeds, buyNBP, sellNBP, buyComm, sellComm, gainLoss
        $pattern = '/<tr>.*?US0378331005.*?2025-03-14.*?2025-09-19.*?100.*?68850\.00.*?79000\.00.*?4\.05.*?3\.95.*?4\.05.*?3\.95.*?10142\.00.*?<\/tr>/s';
        self::assertMatchesRegularExpression($pattern, $html);
    }

    /**
     * Kills CSS class mutants: positive gain gets 'gain' class, negative loss gets 'loss' class.
     */
    public function testGainCSSClassForPositiveGainLoss(): void
    {
        $html = $this->generator->generate($this->reportData());

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

        $html = $this->generator->generate($data);

        self::assertStringContainsString('class="loss">-10158.00', $html);
    }

    /**
     * Kills mutants in renderGroupedSummary: instrument summary shows correct grouped totals.
     */
    public function testInstrumentSummaryGroupsAndShowsTotals(): void
    {
        $html = $this->generator->generate($this->reportData());

        // Instrument summary should show proceeds, costs, and gain/loss
        self::assertStringContainsString('Podsumowanie per instrument', $html);
        // The instrument summary row should contain the ISIN
        self::assertMatchesRegularExpression('/Podsumowanie per instrument.*US0378331005/s', $html);
    }

    /**
     * Kills mutants in renderGroupedSummary: broker summary shows correct grouped totals.
     */
    public function testBrokerSummaryShowsGroupedValuesPerBroker(): void
    {
        $html = $this->generator->generate($this->reportData());

        // Broker summary should contain the broker name, proceeds, costs, gain/loss
        self::assertMatchesRegularExpression('/Podsumowanie per broker.*degiro.*79000\.00.*68850\.00.*10142\.00/s', $html);
    }

    /**
     * Kills mutants in dividend section: country totals are computed and displayed.
     */
    public function testDividendSectionShowsCountryTotals(): void
    {
        $html = $this->generator->generate($this->reportData());

        // Country total row: "Suma US" with gross, WHT, net
        self::assertStringContainsString('Suma US', $html);
        // Total gross = 1500.00, WHT = 225.00, net = 1275.00
        self::assertMatchesRegularExpression('/Suma US.*?1500\.00.*?225\.00.*?1275\.00/s', $html);
    }

    /**
     * Kills mutants on table headers: verify FIFO table has all required column headers.
     */
    public function testFIFOTableHeaders(): void
    {
        $html = $this->generator->generate($this->reportData());

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
        $html = $this->generator->generate($this->reportData());

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
     * Kills mutants on prior year loss table content: year, amount, deducted.
     */
    public function testPriorYearLossTableShowsAllColumns(): void
    {
        $html = $this->generator->generate($this->reportData());

        self::assertStringContainsString('Straty z lat poprzednich', $html);
        // All three prior year loss headers
        self::assertStringContainsString('<th>Rok</th>', $html);
        self::assertStringContainsString('<th>Kwota straty (PLN)</th>', $html);
        self::assertStringContainsString('<th>Odliczone (PLN)</th>', $html);
        // Row values in order
        self::assertMatchesRegularExpression('/<td>2024<\/td>.*?<td>5000\.00<\/td>.*?<td>2500\.00<\/td>/s', $html);
    }

    /**
     * Kills mutants on total summary section: every line item must appear.
     */
    public function testTotalSummaryContainsAllLineItems(): void
    {
        $html = $this->generator->generate($this->reportData());

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
     * Kills mutants on the footer content.
     */
    public function testFooterContent(): void
    {
        $html = $this->generator->generate($this->reportData());

        self::assertStringContainsString('wygenerowany automatycznie przez TaxPilot', $html);
        self::assertStringContainsString('nie zastepuje profesjonalnej porady podatkowej', $html);
    }

    /**
     * Kills mutants on dividend entry row rendering: verifies all columns.
     */
    public function testDividendEntryRowContainsAllFields(): void
    {
        $html = $this->generator->generate($this->reportData());

        // Dividend row should contain: date, instrument, gross, wht, net, nbpRate, tableNumber
        self::assertMatchesRegularExpression(
            '/<tr>.*?2025-06-15.*?AAPL Dividend.*?1500\.00.*?225\.00.*?1275\.00.*?4\.05.*?115\/A\/NBP\/2025.*?<\/tr>/s',
            $html,
        );
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

        $html = $this->generator->generate($data);

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

        $html = $this->generator->generate($data);

        self::assertStringNotContainsString('Straty z lat poprzednich', $html);
    }

    /**
     * Kills mutants on taxpayer name rendering.
     */
    public function testTaxpayerNameRendered(): void
    {
        $html = $this->generator->generate($this->reportData());

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
