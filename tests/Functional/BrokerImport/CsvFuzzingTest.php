<?php

declare(strict_types=1);

namespace App\Tests\Functional\BrokerImport;

use App\BrokerImport\Application\Port\BrokerAdapterInterface;
use App\BrokerImport\Infrastructure\Adapter\Bossa\BossaHistoryAdapter;
use App\BrokerImport\Infrastructure\Adapter\Degiro\DegiroAccountStatementAdapter;
use App\BrokerImport\Infrastructure\Adapter\Degiro\DegiroTransactionsAdapter;
use App\BrokerImport\Infrastructure\Adapter\IBKR\IBKRActivityAdapter;
use App\BrokerImport\Infrastructure\Adapter\Revolut\RevolutStocksAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Fuzzing tests: each adapter must not throw uncaught exceptions
 * when fed malformed or adversarial CSV content.
 *
 * A "pass" means the method either:
 *   - returns normally (including an empty/error ParseResult), or
 *   - throws a domain exception that is a subclass of \RuntimeException or \InvalidArgumentException.
 *
 * A "fail" is any uncaught \Error, \TypeError, or \LogicException that indicates
 * a programming fault, not a data fault.
 */
final class CsvFuzzingTest extends TestCase
{
    private const string FIXTURES_DIR = __DIR__ . '/../../Fixtures';

    /**
     * @return array<string, array{adapter: BrokerAdapterInterface, fixture: string, filename: string}>
     */
    public static function adapterProvider(): array
    {
        return [
            'ibkr' => [
                'adapter' => new IBKRActivityAdapter(),
                'fixture' => self::FIXTURES_DIR . '/ibkr_activity_sample.csv',
                'filename' => 'ibkr_activity_sample.csv',
            ],
            'degiro_transactions' => [
                'adapter' => new DegiroTransactionsAdapter(),
                'fixture' => self::FIXTURES_DIR . '/degiro_transactions_sample.csv',
                'filename' => 'degiro_transactions_sample.csv',
            ],
            'degiro_account_statement' => [
                'adapter' => new DegiroAccountStatementAdapter(),
                'fixture' => self::FIXTURES_DIR . '/degiro_account_statement_sample.csv',
                'filename' => 'degiro_account_statement_sample.csv',
            ],
            'revolut' => [
                'adapter' => new RevolutStocksAdapter(),
                'fixture' => self::FIXTURES_DIR . '/revolut_stocks_sample.csv',
                'filename' => 'revolut_stocks_sample.csv',
            ],
            'bossa' => [
                'adapter' => new BossaHistoryAdapter(),
                'fixture' => self::FIXTURES_DIR . '/bossa_history_sample.csv',
                'filename' => 'bossa_history_sample.csv',
            ],
        ];
    }

    /**
     * @return array<string, array{mutation: string, mutator: \Closure}>
     */
    public static function mutationProvider(): array
    {
        return [
            'truncated_10pct' => [
                'mutation' => 'truncated_10pct',
                'mutator' => static fn (string $c): string => substr($c, 0, (int) (strlen($c) * 0.10)),
            ],
            'truncated_25pct' => [
                'mutation' => 'truncated_25pct',
                'mutator' => static fn (string $c): string => substr($c, 0, (int) (strlen($c) * 0.25)),
            ],
            'truncated_50pct' => [
                'mutation' => 'truncated_50pct',
                'mutator' => static fn (string $c): string => substr($c, 0, (int) (strlen($c) * 0.50)),
            ],
            'truncated_75pct' => [
                'mutation' => 'truncated_75pct',
                'mutator' => static fn (string $c): string => substr($c, 0, (int) (strlen($c) * 0.75)),
            ],
            'truncated_99pct' => [
                'mutation' => 'truncated_99pct',
                'mutator' => static fn (string $c): string => substr($c, 0, (int) (strlen($c) * 0.99)),
            ],
            'binary_garbage_mid' => [
                'mutation' => 'binary_garbage_mid',
                'mutator' => static function (string $c): string {
                    $mid = (int) (strlen($c) / 2);
                    $garbage = "\x00\xFF\xFE\x01\x7F\x80\xC0\xDE\xAD\xBE\xEF";

                    return substr($c, 0, $mid) . $garbage . substr($c, $mid);
                },
            ],
            'oversized_header_row' => [
                'mutation' => 'oversized_header_row',
                'mutator' => static function (string $c): string {
                    $hugeLine = implode(',', array_fill(0, 10_000, 'Col'));

                    return $hugeLine . "\n" . $c;
                },
            ],
            'null_bytes' => [
                'mutation' => 'null_bytes',
                'mutator' => static function (string $c): string {
                    $lines = explode("\n", $c);
                    // Inject null bytes into the second data line (index 1), if present
                    if (isset($lines[1])) {
                        $lines[1] = str_replace(',', ",\x00", $lines[1]);
                    }

                    return implode("\n", $lines);
                },
            ],
            'unicode_bom_and_multibyte_numerics' => [
                'mutation' => 'unicode_bom_and_multibyte_numerics',
                'mutator' => static function (string $c): string {
                    // UTF-8 BOM + replace first ASCII digit with a full-width digit
                    $bom = "\xEF\xBB\xBF";
                    // Full-width zero: U+FF10 = 0xEF 0xBC 0x90
                    $c = str_replace('0', "\xEF\xBC\x90", $c);

                    return $bom . $c;
                },
            ],
            'empty_lines_only' => [
                'mutation' => 'empty_lines_only',
                'mutator' => static fn (string $c): string => str_repeat("\n", 500),
            ],
            'single_column_header' => [
                'mutation' => 'single_column_header',
                'mutator' => static function (string $c): string {
                    $lines = explode("\n", $c);
                    $lines[0] = 'OnlyOneColumn';

                    return implode("\n", $lines);
                },
            ],
            'huge_single_cell' => [
                'mutation' => 'huge_single_cell',
                'mutator' => static function (string $c): string {
                    $lines = explode("\n", $c);
                    if (isset($lines[1])) {
                        $fields = str_getcsv($lines[1], ',', '"', '');
                        if (isset($fields[0])) {
                            $fields[0] = str_repeat('A', 100_000);
                            $lines[1] = implode(',', $fields);
                        }
                    }

                    return implode("\n", $lines);
                },
            ],
            'sql_injection_in_symbol' => [
                'mutation' => 'sql_injection_in_symbol',
                'mutator' => static function (string $c): string {
                    $lines = explode("\n", $c);
                    if (isset($lines[1])) {
                        $fields = str_getcsv($lines[1], ',', '"', '');
                        // Inject into first substantive field (index 0 after header)
                        if (isset($fields[0])) {
                            $fields[0] = "'; DROP TABLE transactions; --";
                        }

                        $lines[1] = implode(',', array_map(
                            static fn (?string $f): string => str_contains((string) $f, ',') ? '"' . (string) $f . '"' : (string) $f,
                            $fields,
                        ));
                    }

                    return implode("\n", $lines);
                },
            ],
            'xss_in_description' => [
                'mutation' => 'xss_in_description',
                'mutator' => static function (string $c): string {
                    $lines = explode("\n", $c);
                    if (isset($lines[1])) {
                        $fields = str_getcsv($lines[1], ',', '"', '');
                        // Inject into last field
                        $last = count($fields) - 1;
                        if ($last >= 0) {
                            $fields[$last] = '<script>alert(1)</script>';
                        }

                        $lines[1] = implode(',', array_map(
                            static fn (?string $f): string => str_contains((string) $f, ',') || str_contains((string) $f, '<')
                                ? '"' . (string) $f . '"'
                                : (string) $f,
                            $fields,
                        ));
                    }

                    return implode("\n", $lines);
                },
            ],
        ];
    }

    /**
     * Cartesian product: adapter x mutation.
     *
     * @return array<string, array{BrokerAdapterInterface, string, string, string}>
     */
    public static function adapterMutationProvider(): array
    {
        $cases = [];

        foreach (self::adapterProvider() as $adapterKey => $adapterData) {
            foreach (self::mutationProvider() as $mutationKey => $mutationData) {
                $key = sprintf('%s__%s', $adapterKey, $mutationKey);
                $cases[$key] = [
                    $adapterData['adapter'],
                    $adapterData['fixture'],
                    $adapterData['filename'],
                    $mutationData['mutation'],
                    $mutationData['mutator'],
                ];
            }
        }

        return $cases;
    }

    /**
     * @param \Closure(string): string $mutator
     */
    #[DataProvider('adapterMutationProvider')]
    public function testAdapterDoesNotCrashOnMutatedInput(
        BrokerAdapterInterface $adapter,
        string $fixturePath,
        string $filename,
        string $mutationName,
        \Closure $mutator,
    ): void {
        $original = file_get_contents($fixturePath);
        self::assertIsString($original, sprintf('Fixture not readable: %s', $fixturePath));

        $mutated = $mutator($original);

        // supports() must never throw — it is a detection method
        $supported = false;
        try {
            $supported = $adapter->supports($mutated, $filename);
        } catch (\Throwable $e) {
            self::fail(sprintf(
                '[%s / %s] supports() threw %s: %s',
                $adapter->brokerId()->toString(),
                $mutationName,
                $e::class,
                $e->getMessage(),
            ));
        }

        if (! $supported) {
            // Adapter correctly rejected the mutated content — nothing more to test.
            $this->addToAssertionCount(1);

            return;
        }

        // If the adapter claims to support the content, parse() must not crash.
        // Domain exceptions (\RuntimeException, \InvalidArgumentException) are acceptable.
        // PHP engine errors (\Error, \TypeError) are bugs.
        try {
            $adapter->parse($mutated);
            $this->addToAssertionCount(1);
        } catch (\RuntimeException | \InvalidArgumentException) {
            // Acceptable: domain-level rejection of malformed data.
            $this->addToAssertionCount(1);
        } catch (\Throwable $e) {
            self::fail(sprintf(
                '[%s / %s] parse() threw unexpected %s: %s',
                $adapter->brokerId()->toString(),
                $mutationName,
                $e::class,
                $e->getMessage(),
            ));
        }
    }
}
