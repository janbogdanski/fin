<?php

declare(strict_types=1);

namespace App\Tests\Unit\BrokerImport;

use App\BrokerImport\Application\DTO\NormalizedTransaction;
use App\BrokerImport\Application\DTO\ParseError;
use App\BrokerImport\Application\DTO\ParseMetadata;
use App\BrokerImport\Application\DTO\ParseResult;
use App\BrokerImport\Application\DTO\ParseWarning;
use App\BrokerImport\Application\DTO\TransactionType;
use App\BrokerImport\Application\Port\BrokerAdapterInterface;
use App\BrokerImport\Infrastructure\Adapter\Bossa\BossaHistoryAdapter;
use App\BrokerImport\Infrastructure\Adapter\Degiro\DegiroAccountStatementAdapter;
use App\BrokerImport\Infrastructure\Adapter\Degiro\DegiroTransactionsAdapter;
use App\BrokerImport\Infrastructure\Adapter\IBKR\IBKRActivityAdapter;
use App\BrokerImport\Infrastructure\Adapter\Revolut\RevolutStocksAdapter;
use App\BrokerImport\Infrastructure\Adapter\Spreadsheet\XlsxWorkbookReader;
use App\BrokerImport\Infrastructure\Adapter\XTB\XTBStatementAdapter;
use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TransactionId;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Schema validation test for broker adapters.
 *
 * Verifies that every BrokerAdapterInterface implementation produces
 * ParseResult objects that conform to the expected schema:
 *
 *   - ParseResult.transactions: list<NormalizedTransaction> with correct field types
 *   - ParseResult.errors: list<ParseError>
 *   - ParseResult.warnings: list<ParseWarning>
 *   - ParseResult.metadata: ParseMetadata with required fields
 *
 * This is a schema validation test, not an HTTP Pact contract.
 * It guards against adapter drift: if an adapter changes its output
 * format, this test catches it before it reaches the tax calculation layer.
 */
final class BrokerAdapterSchemaTest extends TestCase
{
    private const string FIXTURES_DIR = __DIR__ . '/../../Fixtures';

    private const string RESOURCES_DIR = __DIR__ . '/../../../resources';

    /**
     * @return iterable<string, array{BrokerAdapterInterface, string, string}>
     */
    public static function adapterProvider(): iterable
    {
        yield 'revolut' => [
            new RevolutStocksAdapter(),
            self::FIXTURES_DIR . '/revolut_stocks_sample.csv',
            'revolut',
        ];

        yield 'degiro-transactions' => [
            new DegiroTransactionsAdapter(),
            self::FIXTURES_DIR . '/degiro_transactions_sample.csv',
            'degiro',
        ];

        yield 'degiro-account-statement' => [
            new DegiroAccountStatementAdapter(),
            self::FIXTURES_DIR . '/degiro_account_statement_sample.csv',
            'degiro',
        ];

        yield 'ibkr' => [
            new IBKRActivityAdapter(),
            self::FIXTURES_DIR . '/ibkr_activity_sample.csv',
            'ibkr',
        ];

        yield 'bossa' => [
            new BossaHistoryAdapter(),
            self::FIXTURES_DIR . '/bossa_history_sample.csv',
            'bossa',
        ];

        yield 'xtb' => [
            new XTBStatementAdapter(new XlsxWorkbookReader()),
            self::RESOURCES_DIR . '/50726063/PLN_50726063_2024-12-31_2025-12-31.xlsx',
            'xtb',
        ];
    }

    #[DataProvider('adapterProvider')]
    public function testAdapterReturnsValidParseResultSchema(
        BrokerAdapterInterface $adapter,
        string $fixturePath,
        string $expectedBrokerId,
    ): void {
        $fileContent = file_get_contents($fixturePath);
        self::assertNotFalse($fileContent, "Fixture file not found: {$fixturePath}");

        // Precondition: adapter recognizes the fixture
        self::assertTrue(
            $adapter->supports($fileContent, basename($fixturePath)),
            sprintf('%s::supports() should return true for %s', $adapter::class, basename($fixturePath)),
        );

        // Act
        $result = $adapter->parse($fileContent, basename($fixturePath));

        // Contract: ParseResult structure
        self::assertInstanceOf(ParseResult::class, $result);
        $this->assertValidParseResult($result, $expectedBrokerId);
    }

    #[DataProvider('adapterProvider')]
    public function testAdapterProducesAtLeastOneTransaction(
        BrokerAdapterInterface $adapter,
        string $fixturePath,
        string $expectedBrokerId,
    ): void {
        $fileContent = file_get_contents($fixturePath);
        self::assertNotFalse($fileContent);

        $result = $adapter->parse($fileContent, basename($fixturePath));

        self::assertNotEmpty(
            $result->transactions,
            sprintf('%s produced zero transactions from %s', $adapter::class, basename($fixturePath)),
        );
    }

    #[DataProvider('adapterProvider')]
    public function testAdapterBrokerIdMatchesExpected(
        BrokerAdapterInterface $adapter,
        string $fixturePath,
        string $expectedBrokerId,
    ): void {
        self::assertSame($expectedBrokerId, $adapter->brokerId()->toString());
    }

    public function testEmptyContentProducesEmptyResult(): void
    {
        $adapter = new RevolutStocksAdapter();

        // Empty content should not be supported
        self::assertFalse($adapter->supports('', 'empty.csv'));
    }

    private function assertValidParseResult(ParseResult $result, string $expectedBrokerId): void
    {
        // Transactions schema
        foreach ($result->transactions as $index => $tx) {
            $label = sprintf('transaction[%d]', $index);
            $this->assertValidNormalizedTransaction($tx, $label);
        }

        // Errors schema
        foreach ($result->errors as $index => $error) {
            self::assertInstanceOf(ParseError::class, $error, "errors[{$index}]");
            self::assertIsInt($error->lineNumber, "errors[{$index}].lineNumber");
            self::assertIsString($error->section, "errors[{$index}].section");
            self::assertIsString($error->message, "errors[{$index}].message");
            self::assertNotEmpty($error->message, "errors[{$index}].message should not be empty");
        }

        // Warnings schema
        foreach ($result->warnings as $index => $warning) {
            self::assertInstanceOf(ParseWarning::class, $warning, "warnings[{$index}]");
            self::assertIsInt($warning->lineNumber, "warnings[{$index}].lineNumber");
            self::assertIsString($warning->section, "warnings[{$index}].section");
            self::assertIsString($warning->message, "warnings[{$index}].message");
        }

        // Metadata schema
        $meta = $result->metadata;
        self::assertInstanceOf(ParseMetadata::class, $meta);
        self::assertInstanceOf(BrokerId::class, $meta->broker);
        self::assertIsInt($meta->totalTransactions);
        self::assertIsInt($meta->totalErrors);
        self::assertGreaterThanOrEqual(0, $meta->totalTransactions);
        self::assertGreaterThanOrEqual(0, $meta->totalErrors);
        self::assertSame(count($result->transactions), $meta->totalTransactions, 'metadata.totalTransactions mismatch');
        self::assertSame(count($result->errors), $meta->totalErrors, 'metadata.totalErrors mismatch');
        self::assertIsArray($meta->sectionsFound);
        self::assertNotEmpty($meta->sectionsFound, 'metadata.sectionsFound should not be empty');

        // If transactions exist, date range should be set
        if ($result->transactions !== []) {
            self::assertInstanceOf(\DateTimeImmutable::class, $meta->dateFrom, 'metadata.dateFrom');
            self::assertInstanceOf(\DateTimeImmutable::class, $meta->dateTo, 'metadata.dateTo');
            self::assertLessThanOrEqual($meta->dateTo, $meta->dateFrom, 'metadata.dateFrom should be <= dateTo');
        }
    }

    private function assertValidNormalizedTransaction(NormalizedTransaction $tx, string $label): void
    {
        // Identity
        self::assertInstanceOf(TransactionId::class, $tx->id, "{$label}.id");
        self::assertNotEmpty($tx->id->toString(), "{$label}.id should not be empty");

        // Symbol
        self::assertIsString($tx->symbol, "{$label}.symbol");
        self::assertNotEmpty($tx->symbol, "{$label}.symbol should not be empty");

        // Type must be a valid enum
        self::assertInstanceOf(TransactionType::class, $tx->type, "{$label}.type");

        // Date
        self::assertInstanceOf(\DateTimeImmutable::class, $tx->date, "{$label}.date");

        // Quantity must be non-negative
        self::assertInstanceOf(BigDecimal::class, $tx->quantity, "{$label}.quantity");
        self::assertFalse(
            $tx->quantity->isNegative(),
            "{$label}.quantity should be non-negative, got: {$tx->quantity}",
        );

        // Money fields
        self::assertInstanceOf(Money::class, $tx->pricePerUnit, "{$label}.pricePerUnit");
        self::assertInstanceOf(Money::class, $tx->commission, "{$label}.commission");

        // Commission should be non-negative
        self::assertFalse(
            $tx->commission->amount()->isNegative(),
            "{$label}.commission should be non-negative, got: {$tx->commission->amount()}",
        );

        // Broker
        self::assertInstanceOf(BrokerId::class, $tx->broker, "{$label}.broker");
        self::assertNotEmpty($tx->broker->toString(), "{$label}.broker should not be empty");

        // Description
        self::assertIsString($tx->description, "{$label}.description");

        // Raw data
        self::assertIsArray($tx->rawData, "{$label}.rawData");
    }
}
