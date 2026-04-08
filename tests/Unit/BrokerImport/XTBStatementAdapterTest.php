<?php

declare(strict_types=1);

namespace App\Tests\Unit\BrokerImport;

use App\BrokerImport\Application\DTO\NormalizedTransaction;
use App\BrokerImport\Application\DTO\TransactionType;
use App\BrokerImport\Infrastructure\Adapter\Spreadsheet\XlsxWorkbookReader;
use App\BrokerImport\Infrastructure\Adapter\XTB\XTBStatementAdapter;
use App\Shared\Domain\ValueObject\CurrencyCode;
use PHPUnit\Framework\TestCase;

final class XTBStatementAdapterTest extends TestCase
{
    private const string RESOURCES_DIR = __DIR__ . '/../../../resources';

    private XTBStatementAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new XTBStatementAdapter(new XlsxWorkbookReader());
    }

    public function testBrokerIdReturnsXtb(): void
    {
        self::assertSame('xtb', $this->adapter->brokerId()->toString());
    }

    public function testSupportsXtbWorkbookFormat(): void
    {
        $content = $this->readResource('50726063/PLN_50726063_2024-12-31_2025-12-31.xlsx');

        self::assertTrue($this->adapter->supports($content, 'PLN_50726063_2024-12-31_2025-12-31.xlsx'));
    }

    public function testDoesNotSupportPlainCsvContent(): void
    {
        self::assertFalse($this->adapter->supports("Date,Ticker,Type\n2025-01-01,AAPL,BUY", 'xtb.csv'));
    }

    public function testParsesCashOperationsFromPlnWorkbook(): void
    {
        $filename = 'PLN_50726063_2024-12-31_2025-12-31.xlsx';
        $content = $this->readResource('50726063/' . $filename);

        $result = $this->adapter->parse($content, $filename);

        self::assertCount(0, $result->errors, $this->formatErrors($result->errors));
        self::assertCount(78, $result->transactions);

        $typeCounts = $this->countTransactionsByType($result->transactions);
        self::assertSame(24, $typeCounts[TransactionType::BUY->value] ?? 0);
        self::assertSame(2, $typeCounts[TransactionType::SELL->value] ?? 0);
        self::assertSame(26, $typeCounts[TransactionType::DIVIDEND->value] ?? 0);
        self::assertSame(26, $typeCounts[TransactionType::WITHHOLDING_TAX->value] ?? 0);

        self::assertSame('xtb', $result->metadata->broker->toString());
        self::assertSame(78, $result->metadata->totalTransactions);
        self::assertNotEmpty($result->warnings);
        self::assertStringContainsString(
            'Free funds interest',
            implode("\n", array_map(static fn ($warning) => $warning->message, $result->warnings)),
        );
    }

    public function testParsesFractionalTradeQuantityFromEurWorkbook(): void
    {
        $filename = 'EUR_50726148_2024-12-31_2025-12-31.xlsx';
        $content = $this->readResource('50726148/' . $filename);

        $result = $this->adapter->parse($content, $filename);

        self::assertCount(0, $result->errors, $this->formatErrors($result->errors));
        self::assertCount(152, $result->transactions);

        $typeCounts = $this->countTransactionsByType($result->transactions);
        self::assertSame(85, $typeCounts[TransactionType::BUY->value] ?? 0);
        self::assertSame(4, $typeCounts[TransactionType::SELL->value] ?? 0);
        self::assertSame(42, $typeCounts[TransactionType::DIVIDEND->value] ?? 0);
        self::assertSame(21, $typeCounts[TransactionType::WITHHOLDING_TAX->value] ?? 0);

        $fractionalTrade = $this->findTransactionByDescription(
            $result->transactions,
            'OPEN BUY 4/4.9262 @ 111.4500',
        );

        self::assertNotNull($fractionalTrade);
        self::assertSame(TransactionType::BUY, $fractionalTrade->type);
        self::assertSame('4', $fractionalTrade->quantity->__toString());
        self::assertSame(CurrencyCode::EUR, $fractionalTrade->pricePerUnit->currency());
    }

    public function testParsesDividendOnlyUsdWorkbook(): void
    {
        $filename = 'USD_51662274_2024-12-31_2025-12-31.xlsx';
        $content = $this->readResource('51662274/' . $filename);

        $result = $this->adapter->parse($content, $filename);

        self::assertCount(0, $result->errors, $this->formatErrors($result->errors));
        self::assertCount(33, $result->transactions);

        $typeCounts = $this->countTransactionsByType($result->transactions);
        self::assertSame(19, $typeCounts[TransactionType::BUY->value] ?? 0);
        self::assertSame(7, $typeCounts[TransactionType::DIVIDEND->value] ?? 0);
        self::assertSame(7, $typeCounts[TransactionType::WITHHOLDING_TAX->value] ?? 0);
    }

    public function testParsesEmptyIkeWorkbookWithoutErrors(): void
    {
        $filename = 'IKE_52701589_2024-12-31_2025-12-31.xlsx';
        $content = $this->readResource('50726063/' . $filename);

        $result = $this->adapter->parse($content, $filename);

        self::assertSame([], $result->errors);
        self::assertSame([], $result->transactions);
        self::assertSame(0, $result->metadata->totalTransactions);
    }

    public function testSupportsWorkbookWithCashOperationsOnly(): void
    {
        $filename = 'USD_cash_only.xlsx';
        $content = $this->createWorkbook([
            'Cash Operations' => [
                ['Type', 'Ticker', 'Instrument', 'Time', 'Amount', 'Comment'],
                ['Stock purchase', 'AAPL', 'Apple Inc.', '45722.5', '-1500', 'OPEN BUY 10 @ 150.00'],
            ],
        ]);

        self::assertTrue($this->adapter->supports($content, $filename));

        $result = $this->adapter->parse($content, $filename);

        self::assertCount(0, $result->errors, $this->formatErrors($result->errors));
        self::assertCount(1, $result->transactions);
        self::assertSame(['Cash Operations'], $result->metadata->sectionsFound);
        self::assertSame(TransactionType::BUY, $result->transactions[0]->type);
        self::assertSame('10', $result->transactions[0]->quantity->__toString());
    }

    public function testSupportsWorkbookWithClosedPositionsOnly(): void
    {
        $filename = 'USD_closed_positions.xlsx';
        $content = $this->createWorkbook([
            'Closed Positions' => [
                ['Instrument', 'Ticker', 'Type', 'Volume', 'Open Price', 'Open Time (UTC)', 'Close Price', 'Close Time (UTC)', 'Category'],
                ['Apple Inc.', 'AAPL', 'BUY', '2', '150.00', '45722.5', '170.00', '45730.75', 'STOCK'],
            ],
        ]);

        self::assertTrue($this->adapter->supports($content, $filename));

        $result = $this->adapter->parse($content, $filename);

        self::assertCount(0, $result->errors, $this->formatErrors($result->errors));
        self::assertCount(2, $result->transactions);
        self::assertSame(['Closed Positions'], $result->metadata->sectionsFound);

        $typeCounts = $this->countTransactionsByType($result->transactions);
        self::assertSame(1, $typeCounts[TransactionType::BUY->value] ?? 0);
        self::assertSame(1, $typeCounts[TransactionType::SELL->value] ?? 0);
    }

    private function readResource(string $relativePath): string
    {
        $content = file_get_contents(self::RESOURCES_DIR . '/' . $relativePath);
        self::assertNotFalse($content, 'Failed to read XTB resource: ' . $relativePath);

        return $content;
    }

    /**
     * @param array<string, list<list<string>>> $sheets
     */
    private function createWorkbook(array $sheets): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'xtb_workbook_');
        self::assertNotFalse($tmpFile);

        try {
            $zip = new \ZipArchive();
            self::assertTrue($zip->open($tmpFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));

            $zip->addFromString('xl/workbook.xml', $this->buildWorkbookXml(array_keys($sheets)));
            $zip->addFromString('xl/_rels/workbook.xml.rels', $this->buildWorkbookRelationshipsXml(count($sheets)));

            $sheetIndex = 1;

            foreach ($sheets as $rows) {
                $zip->addFromString(
                    sprintf('xl/worksheets/sheet%d.xml', $sheetIndex),
                    $this->buildWorksheetXml($rows),
                );
                $sheetIndex++;
            }

            $zip->close();

            $content = file_get_contents($tmpFile);
            self::assertNotFalse($content);

            return $content;
        } finally {
            @unlink($tmpFile);
        }
    }

    /**
     * @param list<\App\BrokerImport\Application\DTO\NormalizedTransaction> $transactions
     * @return array<string, int>
     */
    private function countTransactionsByType(array $transactions): array
    {
        $counts = [];

        foreach ($transactions as $transaction) {
            $counts[$transaction->type->value] = ($counts[$transaction->type->value] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param list<NormalizedTransaction> $transactions
     */
    private function findTransactionByDescription(array $transactions, string $description): ?NormalizedTransaction
    {
        foreach ($transactions as $transaction) {
            if ($transaction->description === $description) {
                return $transaction;
            }
        }

        return null;
    }

    /**
     * @param list<\App\BrokerImport\Application\DTO\ParseError> $errors
     */
    private function formatErrors(array $errors): string
    {
        return implode("\n", array_map(
            static fn ($error) => sprintf('[Line %d, %s] %s', $error->lineNumber, $error->section, $error->message),
            $errors,
        ));
    }

    /**
     * @param list<string> $sheetNames
     */
    private function buildWorkbookXml(array $sheetNames): string
    {
        $sheetsXml = [];

        foreach ($sheetNames as $index => $sheetName) {
            $sheetId = $index + 1;
            $sheetsXml[] = sprintf(
                '<sheet name="%s" sheetId="%d" r:id="rId%d"/>',
                htmlspecialchars($sheetName, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
                $sheetId,
                $sheetId,
            );
        }

        return sprintf(
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>%s</sheets>'
            . '</workbook>',
            implode('', $sheetsXml),
        );
    }

    private function buildWorkbookRelationshipsXml(int $sheetCount): string
    {
        $relationshipsXml = [];

        for ($sheetIndex = 1; $sheetIndex <= $sheetCount; $sheetIndex++) {
            $relationshipsXml[] = sprintf(
                '<Relationship Id="rId%d" '
                . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" '
                . 'Target="worksheets/sheet%d.xml"/>',
                $sheetIndex,
                $sheetIndex,
            );
        }

        return sprintf(
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">%s</Relationships>',
            implode('', $relationshipsXml),
        );
    }

    /**
     * @param list<list<string>> $rows
     */
    private function buildWorksheetXml(array $rows): string
    {
        $rowXml = [];

        foreach ($rows as $rowIndex => $row) {
            $cellXml = [];

            foreach ($row as $columnIndex => $value) {
                $cellXml[] = sprintf(
                    '<c r="%s%d" t="inlineStr"><is><t>%s</t></is></c>',
                    $this->columnName($columnIndex + 1),
                    $rowIndex + 1,
                    htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
                );
            }

            $rowXml[] = sprintf('<row r="%d">%s</row>', $rowIndex + 1, implode('', $cellXml));
        }

        return sprintf(
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>%s</sheetData>'
            . '</worksheet>',
            implode('', $rowXml),
        );
    }

    private function columnName(int $index): string
    {
        $name = '';

        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }

        return $name;
    }
}
