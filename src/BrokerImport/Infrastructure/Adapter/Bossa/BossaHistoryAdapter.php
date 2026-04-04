<?php

declare(strict_types=1);

namespace App\BrokerImport\Infrastructure\Adapter\Bossa;

use App\BrokerImport\Application\DTO\NormalizedTransaction;
use App\BrokerImport\Application\DTO\ParseError;
use App\BrokerImport\Application\DTO\ParseResult;
use App\BrokerImport\Application\DTO\ParseWarning;
use App\BrokerImport\Application\DTO\TransactionType;
use App\BrokerImport\Application\Port\BrokerAdapterInterface;
use App\BrokerImport\Infrastructure\Adapter\CsvSanitizer;
use App\BrokerImport\Infrastructure\Adapter\ParseResultBuilder;
use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TransactionId;
use Brick\Math\BigDecimal;

/**
 * Parses Bossa (Dom Maklerski BOŚ) transaction history CSV export.
 *
 * Key characteristics:
 *   - Semicolon (;) delimiter
 *   - Windows-1250 (CP1250) encoding for Polish characters
 *   - Polish decimal separator: comma (123,45)
 *   - Side (Strona): "K" = buy, "S" = sell
 *
 * Supports header variants:
 *   - "Data operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja"
 *   - "Data;Nazwa instrumentu;Typ;Liczba;Cena;Wartość transakcji;Prowizja;Waluta"
 */
final readonly class BossaHistoryAdapter implements BrokerAdapterInterface
{
    use CsvSanitizer;
    use ParseResultBuilder;

    private const string BROKER_ID = 'bossa';

    /**
     * @var list<list<string>> Header detection keywords (any set matching = supported)
     */
    private const array SUPPORTED_HEADER_SETS = [
        ['Data operacji', 'Instrument', 'Strona'],
        ['Data', 'Nazwa instrumentu', 'Typ'],
    ];

    /**
     * @var array<string, TransactionType>
     */
    private const array SIDE_MAP = [
        'K' => TransactionType::BUY,
        'S' => TransactionType::SELL,
        'KUPNO' => TransactionType::BUY,
        'SPRZEDAŻ' => TransactionType::SELL,
        'SPRZEDAZ' => TransactionType::SELL,
    ];

    public function brokerId(): BrokerId
    {
        return BrokerId::of(self::BROKER_ID);
    }

    public function priority(): int
    {
        return 100;
    }

    public function supports(string $content, string $filename): bool
    {
        if ($content === '') {
            return false;
        }

        $content = $this->stripBom($content);
        $content = $this->ensureUtf8($content);
        $firstLine = strtok($content, "\n");

        if ($firstLine === false) {
            return false;
        }

        $headers = array_map(
            static fn (string|null $h): string => \trim((string) $h),
            str_getcsv($firstLine, ';', '"', ''),
        );

        foreach (self::SUPPORTED_HEADER_SETS as $requiredHeaders) {
            if (array_intersect($requiredHeaders, $headers) === $requiredHeaders) {
                return true;
            }
        }

        return false;
    }

    public function parse(string $csvContent): ParseResult
    {
        $csvContent = $this->stripBom($csvContent);
        $csvContent = $this->ensureUtf8($csvContent);
        // TODO: P2-028 — replace explode() with streaming (fgets/SplFileObject) to reduce memory footprint
        $lines = explode("\n", $csvContent);
        $transactions = [];
        $errors = [];
        $warnings = [];

        if ($lines === [] || trim($lines[0]) === '') {
            return $this->buildParseResult($transactions, $errors, $warnings, ['Trades']);
        }

        $headers = array_map(
            static fn (string|null $h): string => \trim((string) $h),
            str_getcsv(trim($lines[0]), ';', '"', ''),
        );

        for ($i = 1, $count = count($lines); $i < $count; $i++) {
            $line = trim($lines[$i]);

            if ($line === '') {
                continue;
            }

            $fields = array_map(fn ($v) => (string) $v, str_getcsv($line, ';', '"', ''));
            $mapped = $this->mapFieldsToHeaders($headers, $fields);
            $lineNumber = $i + 1;

            try {
                $tx = $this->buildTransaction($mapped, $lineNumber, $warnings);

                if ($tx !== null) {
                    $transactions[] = $tx;
                }
            } catch (\Throwable $e) {
                $errors[] = new ParseError(
                    lineNumber: $lineNumber,
                    section: 'Trades',
                    message: $e->getMessage(),
                    rawData: array_map($this->sanitize(...), $mapped),
                );
            }
        }

        return $this->buildParseResult($transactions, $errors, $warnings, ['Trades']);
    }

    /**
     * @param array<string, string> $mapped
     * @param list<ParseWarning> $warnings
     */
    private function buildTransaction(array $mapped, int $lineNumber, array &$warnings): ?NormalizedTransaction
    {
        $rawSide = strtoupper(trim($mapped['Strona'] ?? $mapped['Typ'] ?? ''));
        $type = self::SIDE_MAP[$rawSide] ?? null;

        if ($type === null) {
            $warnings[] = new ParseWarning(
                lineNumber: $lineNumber,
                section: 'Trades',
                message: sprintf('Unknown transaction side: "%s", skipping row', $rawSide),
            );

            return null;
        }

        $symbol = $this->sanitize($mapped['Instrument'] ?? $mapped['Nazwa instrumentu'] ?? '');
        $currencyCode = $this->resolveCurrency($mapped['Waluta'] ?? 'PLN');
        $quantity = BigDecimal::of($this->normalizeDecimal($mapped['Ilość'] ?? $mapped['Liczba'] ?? '0'))->abs();
        $price = $this->normalizeDecimal($mapped['Kurs'] ?? $mapped['Cena'] ?? '0');
        $commission = $this->normalizeDecimal($mapped['Prowizja'] ?? '0');
        $date = $this->parseDate($mapped['Data operacji'] ?? $mapped['Data'] ?? '');
        $isin = $this->tryExtractISIN($mapped, $lineNumber, $warnings);

        return new NormalizedTransaction(
            id: TransactionId::generate(),
            isin: $isin,
            symbol: $symbol,
            type: $type,
            date: $date,
            quantity: $quantity,
            pricePerUnit: Money::of($price, $currencyCode),
            commission: Money::of(BigDecimal::of($commission)->abs()->__toString(), $currencyCode),
            broker: $this->brokerId(),
            description: $this->sanitize(
                sprintf('Bossa: %s %s %s @ %s', $type->value, $quantity, $symbol, $price),
            ),
            rawData: array_map($this->sanitize(...), $mapped),
        );
    }

    /**
     * Converts Windows-1250 encoded content to UTF-8 if needed.
     */
    private function ensureUtf8(string $content): string
    {
        if (\mb_detect_encoding($content, 'UTF-8', true) !== false) {
            return $content;
        }

        // iconv supports Windows-1250, mb_convert_encoding does not in Alpine PHP
        $converted = @\iconv('Windows-1250', 'UTF-8//TRANSLIT', $content);

        return \is_string($converted) ? $converted : $content;
    }

    /**
     * Normalizes Polish decimal format (comma → dot).
     * Handles values like "123,45" → "123.45" and "1 234,56" → "1234.56".
     */
    private function normalizeDecimal(string $value): string
    {
        $value = trim($value);
        // Remove spaces used as thousands separator
        $value = str_replace(' ', '', $value);
        // Replace comma decimal separator with dot
        $value = str_replace(',', '.', $value);

        return $value;
    }

    private function parseDate(string $value): \DateTimeImmutable
    {
        $value = trim($value);
        $formats = ['Y-m-d', 'd.m.Y', 'd/m/Y', 'Y-m-d H:i:s'];

        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);

            if ($date !== false) {
                return $date;
            }
        }

        throw new \InvalidArgumentException(sprintf('Cannot parse date: "%s"', $value));
    }

    private function resolveCurrency(string $code): CurrencyCode
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            return CurrencyCode::PLN;
        }

        $currency = CurrencyCode::tryFrom($code);

        if ($currency === null) {
            throw new \InvalidArgumentException(sprintf('Unsupported currency code: "%s"', $code));
        }

        return $currency;
    }

    /**
     * @param array<string, string> $mapped
     * @param list<ParseWarning> $warnings
     */
    private function tryExtractISIN(array $mapped, int $lineNumber, array &$warnings): ?ISIN
    {
        $candidate = trim($mapped['ISIN'] ?? '');

        if ($candidate !== '') {
            try {
                return ISIN::fromString($candidate);
            } catch (\InvalidArgumentException) {
                $warnings[] = new ParseWarning(
                    lineNumber: $lineNumber,
                    section: 'Trades',
                    message: sprintf('Invalid ISIN: "%s"', $candidate),
                );
            }
        } else {
            $symbol = $mapped['Instrument'] ?? $mapped['Nazwa instrumentu'] ?? '';
            $warnings[] = new ParseWarning(
                lineNumber: $lineNumber,
                section: 'Trades',
                message: sprintf('ISIN not available for instrument "%s"', $symbol),
            );
        }

        return null;
    }

    /**
     * @param list<string> $headers
     * @param list<string> $fields
     * @return array<string, string>
     */
    private function mapFieldsToHeaders(array $headers, array $fields): array
    {
        $mapped = [];

        foreach ($headers as $index => $header) {
            $mapped[$header] = trim((string) ($fields[$index] ?? ''));
        }

        return $mapped;
    }
}
