<?php

declare(strict_types=1);

namespace App\Tests\Unit\BrokerImport;

use App\BrokerImport\Application\DTO\TransactionType;
use App\BrokerImport\Infrastructure\Adapter\Bossa\BossaHistoryAdapter;
use PHPUnit\Framework\TestCase;

final class BossaHistoryAdapterTest extends TestCase
{
    private BossaHistoryAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new BossaHistoryAdapter();
    }

    public function testBrokerIdReturnsBossa(): void
    {
        self::assertSame('bossa', $this->adapter->brokerId()->toString());
    }

    public function testSupportsBossaFormat(): void
    {
        $content = "Data operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja;Waluta;ISIN\n2024-03-15;CDR;K;10;350,00;3500,00;15,50;PLN;PLOPTTC00011";

        self::assertTrue($this->adapter->supports($content, 'bossa_history.csv'));
    }

    public function testSupportsAlternativeHeaderFormat(): void
    {
        $content = "Data;Nazwa instrumentu;Typ;Liczba;Cena;Wartość transakcji;Prowizja;Waluta\n2024-03-15;CDR;K;10;350,00;3500,00;15,50;PLN";

        self::assertTrue($this->adapter->supports($content, 'bossa.csv'));
    }

    public function testDoesNotSupportOtherFormat(): void
    {
        $content = "Date,Ticker,Type,Quantity,Price per share,Total Amount,Currency\n2024-03-15,AAPL,BUY,10,171.25,1712.50,USD";

        self::assertFalse($this->adapter->supports($content, 'revolut.csv'));
    }

    public function testDoesNotSupportEmptyContent(): void
    {
        self::assertFalse($this->adapter->supports('', 'bossa.csv'));
    }

    public function testParsesBuyTransaction(): void
    {
        $csv = "Data operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja;Waluta;ISIN\n2024-03-15;CDR;K;10;350,00;3500,00;15,50;PLN;PLOPTTC00011";

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertCount(0, $result->errors);

        $tx = $result->transactions[0];
        self::assertSame(TransactionType::BUY, $tx->type);
        self::assertSame('CDR', $tx->symbol);
        self::assertTrue($tx->quantity->isEqualTo('10'));
        self::assertTrue($tx->pricePerUnit->amount()->isEqualTo('350.00'));
        self::assertTrue($tx->commission->amount()->isEqualTo('15.50'));
        self::assertSame('PLN', $tx->pricePerUnit->currency()->value);
        self::assertSame('bossa', $tx->broker->toString());
    }

    public function testParsesSellTransaction(): void
    {
        $csv = "Data operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja;Waluta;ISIN\n2024-06-20;PKN;S;20;55,75;1115,00;12,30;PLN;PLPKN0000018";

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);

        $tx = $result->transactions[0];
        self::assertSame(TransactionType::SELL, $tx->type);
        self::assertSame('PKN', $tx->symbol);
        self::assertTrue($tx->quantity->isEqualTo('20'));
        self::assertTrue($tx->pricePerUnit->amount()->isEqualTo('55.75'));
    }

    public function testHandlesSemicolonDelimiter(): void
    {
        // Key: semicolons separate fields, not commas
        $csv = "Data operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja;Waluta;ISIN\n2024-03-15;CDR;K;10;350,00;3500,00;15,50;PLN;PLOPTTC00011";

        $result = $this->adapter->parse($csv);

        // If semicolons are handled correctly, we get one clean transaction
        self::assertCount(1, $result->transactions);
        self::assertSame('CDR', $result->transactions[0]->symbol);
    }

    public function testHandlesWindows1250Encoding(): void
    {
        // Build a CSV with Polish characters in Windows-1250 encoding
        $utf8 = "Data operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja;Waluta\n2024-03-15;CDR;K;10;350,00;3500,00;15,50;PLN";
        $cp1250 = \iconv('UTF-8', 'Windows-1250', $utf8);
        self::assertIsString($cp1250);

        $result = $this->adapter->parse($cp1250);

        self::assertCount(1, $result->transactions);
        self::assertSame('CDR', $result->transactions[0]->symbol);
    }

    public function testHandlesPolishDecimalSeparator(): void
    {
        $csv = "Data operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja;Waluta\n2024-03-15;CDR;K;10;123,45;1234,50;15,99;PLN";

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);

        $tx = $result->transactions[0];
        // Polish "123,45" must become "123.45"
        self::assertTrue($tx->pricePerUnit->amount()->isEqualTo('123.45'));
        self::assertTrue($tx->commission->amount()->isEqualTo('15.99'));
    }

    public function testHandlesDotDateFormat(): void
    {
        $csv = "Data operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja;Waluta\n15.03.2024;CDR;K;10;350,00;3500,00;15,50;PLN";

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertSame('2024-03-15', $result->transactions[0]->date->format('Y-m-d'));
    }

    public function testHandlesISINWhenAvailable(): void
    {
        $csv = "Data operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja;Waluta;ISIN\n2024-03-15;CDR;K;10;350,00;3500,00;15,50;PLN;PLOPTTC00011";

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertNotNull($result->transactions[0]->isin);
        self::assertSame('PLOPTTC00011', $result->transactions[0]->isin->toString());
    }

    public function testHandlesMissingISINWithWarning(): void
    {
        $csv = "Data operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja;Waluta\n2024-03-15;CDR;K;10;350,00;3500,00;15,50;PLN";

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertNull($result->transactions[0]->isin);

        $isinWarnings = array_filter(
            $result->warnings,
            static fn ($w) => str_contains($w->message, 'ISIN not available'),
        );
        self::assertNotEmpty($isinWarnings);
    }

    public function testDefaultCurrencyIsPLN(): void
    {
        // No currency column — should default to PLN
        $csv = "Data operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja\n2024-03-15;CDR;K;10;350,00;3500,00;15,50";

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertSame('PLN', $result->transactions[0]->pricePerUnit->currency()->value);
    }

    public function testSanitizesCsvInjection(): void
    {
        $csv = "Data operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja;Waluta\n2024-03-15;=CMD('calc');K;10;350,00;3500,00;15,50;PLN";

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);

        $tx = $result->transactions[0];
        self::assertStringNotContainsString('=CMD', $tx->symbol);
        self::assertStringStartsNotWith('=', $tx->symbol);

        foreach ($tx->rawData as $value) {
            self::assertStringStartsNotWith('=', $value);
            self::assertStringStartsNotWith('+', $value);
            self::assertStringStartsNotWith('-', $value);
            self::assertStringStartsNotWith('@', $value);
        }
    }

    public function testSkipsUnknownSide(): void
    {
        $csv = "Data operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja;Waluta\n2024-03-15;CDR;X;10;350,00;3500,00;15,50;PLN";

        $result = $this->adapter->parse($csv);

        self::assertCount(0, $result->transactions);
        self::assertNotEmpty($result->warnings);
    }

    public function testReturnsCorrectMetadata(): void
    {
        $csv = "Data operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja;Waluta\n2024-03-15;CDR;K;10;350,00;3500,00;15,50;PLN\n2024-06-20;PKN;S;20;55,75;1115,00;12,30;PLN";

        $result = $this->adapter->parse($csv);

        self::assertSame('bossa', $result->metadata->broker->toString());
        self::assertSame(2, $result->metadata->totalTransactions);
        self::assertSame(0, $result->metadata->totalErrors);
        self::assertNotNull($result->metadata->dateFrom);
        self::assertNotNull($result->metadata->dateTo);
    }

    public function testHandlesInvalidDateGracefully(): void
    {
        $csv = "Data operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja;Waluta;ISIN\nnot-a-date;CDR;K;10;350,00;3500,00;15,50;PLN;PLOPTTC00011";

        $result = $this->adapter->parse($csv);

        self::assertCount(0, $result->transactions);
        self::assertCount(1, $result->errors);
        self::assertStringContainsString('Cannot parse date', $result->errors[0]->message);
    }

    public function testFullSampleFile(): void
    {
        $fixturePath = __DIR__ . '/../../Fixtures/bossa_history_sample.csv';
        $csvContent = file_get_contents($fixturePath);
        self::assertIsString($csvContent);

        self::assertTrue($this->adapter->supports($csvContent, 'bossa_history_sample.csv'));

        $result = $this->adapter->parse($csvContent);

        self::assertCount(0, $result->errors, $this->formatErrors($result->errors));
        self::assertCount(3, $result->transactions);

        $types = array_map(
            static fn ($tx) => $tx->type,
            $result->transactions,
        );

        self::assertCount(2, array_filter($types, static fn ($t) => $t === TransactionType::BUY));
        self::assertCount(1, array_filter($types, static fn ($t) => $t === TransactionType::SELL));

        // All should have ISIN
        foreach ($result->transactions as $tx) {
            self::assertNotNull($tx->isin, sprintf('Expected ISIN for %s', $tx->symbol));
        }

        self::assertSame('bossa', $result->metadata->broker->toString());
        self::assertSame(3, $result->metadata->totalTransactions);
    }

    /**
     * @param list<\App\BrokerImport\Application\DTO\ParseError> $errors
     */
    private function formatErrors(array $errors): string
    {
        return implode("\n", array_map(
            static fn ($e) => sprintf('[Line %d, %s] %s', $e->lineNumber, $e->section, $e->message),
            $errors,
        ));
    }
}
