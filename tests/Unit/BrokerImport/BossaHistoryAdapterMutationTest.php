<?php

declare(strict_types=1);

namespace App\Tests\Unit\BrokerImport;

use App\BrokerImport\Application\DTO\TransactionType;
use App\BrokerImport\Infrastructure\Adapter\Bossa\BossaHistoryAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Mutation-killing tests for BossaHistoryAdapter.
 * Targets: priority(), alternate header coalescing, line numbers,
 * normalizeDecimal (trim, space removal, comma replacement),
 * resolveCurrency (strtoupper, trim, empty), ensureUtf8 iconv,
 * tryExtractISIN coalesce, mapFieldsToHeaders trim/cast.
 */
final class BossaHistoryAdapterMutationTest extends TestCase
{
    private BossaHistoryAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new BossaHistoryAdapter();
    }

    public function testPriorityReturnsExactly100(): void
    {
        self::assertSame(100, $this->adapter->priority());
    }

    /**
     * Kills coalesce order mutants: when alternate header set is used,
     * fields are read from alternate column names.
     * Bossa has two header variants:
     *   - "Data operacji;Instrument;Strona;Ilosc;Kurs;Wartosc;Prowizja;Waluta"
     *   - "Data;Nazwa instrumentu;Typ;Liczba;Cena;Wartosc transakcji;Prowizja;Waluta"
     *
     * Tests with alternate set kill coalesce mutants that swap field name order.
     */
    public function testParsesAlternateHeaderVariant(): void
    {
        $csv = "Data;Nazwa instrumentu;Typ;Liczba;Cena;Wartość transakcji;Prowizja;Waluta\n"
            . '2024-03-15;PKO BP;KUPNO;25;45,50;1137,50;10,00;PLN';

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        $tx = $result->transactions[0];
        self::assertSame(TransactionType::BUY, $tx->type);
        self::assertSame('PKO BP', $tx->symbol);
        self::assertTrue($tx->quantity->isEqualTo('25'));
        self::assertTrue($tx->pricePerUnit->amount()->isEqualTo('45.50'));
        self::assertTrue($tx->commission->amount()->isEqualTo('10.00'));
    }

    /**
     * Kills mutant: $rawSide = strtoupper(trim(...)); if strtoupper is removed,
     * 'K' already uppercase would still work, but 'KUPNO'/'SPRZEDAZ' would be
     * detected via the SIDE_MAP keys which are already uppercase.
     * We test with 'sprzedaz' (lowercase) in Typ column.
     */
    public function testParsesLowerCaseSideInAlternateFormat(): void
    {
        $csv = "Data;Nazwa instrumentu;Typ;Liczba;Cena;Wartość transakcji;Prowizja;Waluta\n"
            . "2024-03-15;CDR;kupno;10;350,00;3500,00;15,50;PLN\n"
            . '2024-06-20;CDR;sprzedaz;10;400,00;4000,00;12,00;PLN';

        $result = $this->adapter->parse($csv);

        // Without strtoupper, 'kupno'/'sprzedaz' don't match SIDE_MAP keys
        self::assertCount(2, $result->transactions);
        self::assertSame(TransactionType::BUY, $result->transactions[0]->type);
        self::assertSame(TransactionType::SELL, $result->transactions[1]->type);
    }

    /**
     * Kills lineNumber mutants: $lineNumber = $i + 1 (changed to $i+0 or $i+2 or $i-1).
     * Assert that error/warning line numbers are correct.
     */
    public function testErrorLineNumberIsCorrect(): void
    {
        $csv = "Data operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja;Waluta\n"
            . "2024-03-15;CDR;K;10;350,00;3500,00;15,50;PLN\n"
            . 'invalid-date;PKN;S;20;55,75;1115,00;12,30;PLN';

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertCount(1, $result->errors);
        // Line 3 = second data row (header=1, first data=2, second data=3)
        self::assertSame(3, $result->errors[0]->lineNumber);
    }

    public function testWarningLineNumberForUnknownSide(): void
    {
        $csv = "Data operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja;Waluta\n"
            . "2024-03-15;CDR;K;10;350,00;3500,00;15,50;PLN\n"
            . '2024-06-20;PKN;EXCHANGE;20;55,75;1115,00;12,30;PLN';

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        $sideWarnings = array_filter(
            $result->warnings,
            static fn ($w) => str_contains($w->message, 'Unknown transaction side'),
        );
        self::assertCount(1, $sideWarnings);
        // The unknown side is on line 3
        $warning = array_values($sideWarnings)[0];
        self::assertSame(3, $warning->lineNumber);
    }

    /**
     * Kills normalizeDecimal mutants:
     * - UnwrapTrim: values with leading spaces
     * - UnwrapStrReplace(' ',''):  thousands separator
     * - str_replace(',','.'): comma decimal
     */
    public function testNormalizesDecimalWithThousandsSeparator(): void
    {
        $csv = "Data operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja;Waluta\n"
            . '2024-03-15;CDR;K;1 000;1 350,55;1 350 550,00;150,50;PLN';

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        $tx = $result->transactions[0];
        self::assertTrue($tx->quantity->isEqualTo('1000'));
        self::assertTrue($tx->pricePerUnit->amount()->isEqualTo('1350.55'));
        self::assertTrue($tx->commission->amount()->isEqualTo('150.50'));
    }

    /**
     * Kills resolveCurrency mutants: test with explicit non-PLN currency.
     */
    public function testResolvesNonPLNCurrency(): void
    {
        $csv = "Data operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja;Waluta\n"
            . '2024-03-15;AAPL;K;10;171,25;1712,50;1,00;USD';

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertSame('USD', $result->transactions[0]->pricePerUnit->currency()->value);
    }

    /**
     * Kills resolveCurrency mutants: strtoupper(trim($code)).
     * If strtoupper is removed, lowercase 'usd' would fail CurrencyCode::from().
     */
    public function testResolvesLowercaseCurrency(): void
    {
        $csv = "Data operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja;Waluta\n"
            . '2024-03-15;AAPL;K;10;171,25;1712,50;1,00;usd';

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertSame('USD', $result->transactions[0]->pricePerUnit->currency()->value);
    }

    /**
     * Kills Identical mutant on resolveCurrency: $code === '' changed to $code !== ''.
     */
    public function testResolvesEmptyCurrencyToPLN(): void
    {
        // When Waluta column is empty, default to PLN
        $csv = "Data operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja;Waluta\n"
            . '2024-03-15;CDR;K;10;350,00;3500,00;15,50;';

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertSame('PLN', $result->transactions[0]->pricePerUnit->currency()->value);
    }

    /**
     * Kills ensureUtf8 Ternary swap mutant: returns $content instead of $converted.
     */
    public function testEnsureUtf8ConvertsWindowsEncoding(): void
    {
        // Build CSV with Polish-specific chars in Windows-1250 that differ from UTF-8
        $utf8Line = "Data operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja;Waluta\n"
            . '2024-03-15;SPÓŁKA;K;10;350,00;3500,00;15,50;PLN';

        $cp1250 = @\iconv('UTF-8', 'Windows-1250', $utf8Line);
        self::assertIsString($cp1250);

        // If the ternary is swapped (returns original instead of converted), Polish chars would be garbled
        $result = $this->adapter->parse($cp1250);

        self::assertCount(1, $result->transactions);
        // The symbol should contain the properly converted UTF-8 string
        self::assertStringContainsString('KA', $result->transactions[0]->symbol);
    }

    /**
     * Kills FalseValue mutant: strtok returns false changed to true.
     */
    public function testSupportsReturnsFalseForSingleNewline(): void
    {
        self::assertFalse($this->adapter->supports("\n", 'bossa.csv'));
    }

    /**
     * Kills tryExtractISIN Coalesce: with alternate header, ISIN warning uses correct symbol field.
     */
    public function testISINWarningUsesCorrectSymbolFromAlternateHeader(): void
    {
        $csv = "Data;Nazwa instrumentu;Typ;Liczba;Cena;Wartość transakcji;Prowizja;Waluta\n"
            . '2024-03-15;Orlen SA;KUPNO;10;70,00;700,00;5,00;PLN';

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);

        $isinWarnings = array_filter(
            $result->warnings,
            static fn ($w) => str_contains($w->message, 'ISIN not available'),
        );
        self::assertNotEmpty($isinWarnings);

        // Should contain the symbol from the alternate header's column name
        $warning = array_values($isinWarnings)[0];
        self::assertStringContainsString('Orlen SA', $warning->message);
    }

    /**
     * Kills LogicalOr mutant: $lines === [] || trim($lines[0]) === ''.
     * If changed to &&, a file with only whitespace first line would not early-return.
     */
    public function testParsingWhitespaceOnlyFirstLineReturnsEmpty(): void
    {
        $result = $this->adapter->parse("   \n");

        self::assertCount(0, $result->transactions);
    }

    /**
     * Kills multiple date format mutants by testing d/m/Y format.
     */
    public function testParsesSlashDateFormat(): void
    {
        $csv = "Data operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja;Waluta\n"
            . '15/03/2024;CDR;K;10;350,00;3500,00;15,50;PLN';

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertSame('2024-03-15', $result->transactions[0]->date->format('Y-m-d'));
    }

    /**
     * Kills InvalidISIN warning path.
     */
    public function testInvalidISINGeneratesWarning(): void
    {
        $csv = "Data operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja;Waluta;ISIN\n"
            . '2024-03-15;CDR;K;10;350,00;3500,00;15,50;PLN;INVALID_ISIN';

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertNull($result->transactions[0]->isin);

        $isinWarnings = array_filter(
            $result->warnings,
            static fn ($w) => str_contains($w->message, 'Invalid ISIN'),
        );
        self::assertNotEmpty($isinWarnings);
    }

    /**
     * Kills sanitize mutant on rawData in error path: array_map($this->sanitize(...), $mapped)
     * vs just $mapped.
     */
    public function testErrorRawDataIsSanitized(): void
    {
        $csv = "Data operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja;Waluta\n"
            . 'invalid-date;=CMD("calc");K;10;350,00;3500,00;15,50;PLN';

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->errors);
        self::assertNotNull($result->errors[0]->rawData);

        foreach ($result->errors[0]->rawData as $value) {
            self::assertStringStartsNotWith('=', $value);
        }
    }
}
