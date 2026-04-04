<?php

declare(strict_types=1);

namespace App\Tests\GoldenDataset;

use App\Declaration\Domain\DTO\PIT38Data;
use App\Declaration\Domain\Service\PIT38XMLGenerator;
use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\NBPRate;
use App\Shared\Domain\ValueObject\TransactionId;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Domain\Model\AnnualTaxCalculation;
use App\TaxCalc\Domain\Model\TaxPositionLedger;
use App\TaxCalc\Domain\Service\CurrencyConverter;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

/**
 * XML Snapshot test for PIT-38 — GoldenDataset001 "Tomasz" canonical scenario.
 *
 * Generates the PIT-38 XML and compares it against the committed golden snapshot
 * at tests/Fixtures/golden/pit38_tomasz_2025.xml.
 *
 * First-run behaviour: if the snapshot file does not exist, it is created and
 * the test passes (bootstrapping). On subsequent runs the output must match
 * the snapshot byte-for-byte (after line-ending normalisation).
 *
 * To intentionally update the snapshot after a generator change:
 *   1. Delete tests/Fixtures/golden/pit38_tomasz_2025.xml
 *   2. Run this test once to regenerate
 *   3. Review the diff, commit the new snapshot
 */
final class GoldenXMLSnapshotTest extends TestCase
{
    private const SNAPSHOT_PATH = __DIR__ . '/../Fixtures/golden/pit38_tomasz_2025.xml';

    private const XSD_PATH = __DIR__ . '/../Fixtures/pit38_minimal.xsd';

    public function testPIT38XmlMatchesGoldenSnapshot(): void
    {
        $xml = $this->generateTomaszXml();

        $this->assertValidXsd($xml);

        if (! file_exists(self::SNAPSHOT_PATH)) {
            $this->writeSnapshot($xml);
            $this->addWarning(
                'Snapshot did not exist — created at ' . self::SNAPSHOT_PATH . '. '
                . 'Review and commit the file to lock-in the expected output.',
            );

            return;
        }

        $snapshot = $this->normalise((string) file_get_contents(self::SNAPSHOT_PATH));
        $actual   = $this->normalise($xml);

        self::assertSame(
            $snapshot,
            $actual,
            'PIT-38 XML output does not match the golden snapshot. '
            . 'If the change is intentional, delete the snapshot file and re-run to regenerate.',
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Reproduce the GoldenDataset001 "Tomasz" scenario end-to-end
     * and return the generated PIT-38 XML string.
     *
     * The inputs are identical to GoldenDataset001TomaszTest so both tests
     * always agree on the canonical values:
     *   Buy 100 AAPL @ $170, commission $1, NBP 4.05 (2025-03-14)
     *   Sell 100 AAPL @ $200, commission $1, NBP 3.95 (2025-09-19)
     */
    private function generateTomaszXml(): string
    {
        $ledger = TaxPositionLedger::create(
            UserId::generate(),
            ISIN::fromString('US0378331005'),
            TaxCategory::EQUITY,
        );

        $buyRate = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('4.05'),
            new \DateTimeImmutable('2025-03-14'),
            '052/A/NBP/2025',
        );
        $sellRate = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('3.95'),
            new \DateTimeImmutable('2025-09-19'),
            '183/A/NBP/2025',
        );

        $converter = new CurrencyConverter();

        $ledger->registerBuy(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-03-15'),
            BigDecimal::of('100'),
            Money::of('170.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $buyRate,
            $converter,
        );

        $closedPositions = $ledger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-09-20'),
            BigDecimal::of('100'),
            Money::of('200.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $sellRate,
            $converter,
        );

        $calc = AnnualTaxCalculation::create(UserId::generate(), TaxYear::of(2025));
        $calc->addClosedPositions($closedPositions, TaxCategory::EQUITY);
        $calc->finalize();

        $equityGainLoss = $calc->equityGainLoss();
        $equityCosts = BigDecimal::of($calc->equityCostBasis()->__toString())
            ->plus(BigDecimal::of($calc->equityCommissions()->__toString()))
            ->toScale(2)
            ->__toString();

        $pit38Data = new PIT38Data(
            taxYear: 2025,
            nip: '5260000005',
            firstName: 'Tomasz',
            lastName: 'Kowalski',
            equityProceeds: $calc->equityProceeds()->toScale(2)->__toString(),
            equityCosts: $equityCosts,
            equityIncome: $equityGainLoss->isPositive() ? $equityGainLoss->toScale(2)->__toString() : '0',
            equityLoss: $equityGainLoss->isNegative() ? $equityGainLoss->abs()->toScale(2)->__toString() : '0',
            equityTaxBase: $calc->equityTaxableIncome()->__toString(),
            equityTax: $calc->equityTax()->__toString(),
            dividendGross: '0',
            dividendWHT: '0',
            dividendTaxDue: '0',
            cryptoProceeds: '0',
            cryptoCosts: '0',
            cryptoIncome: '0',
            cryptoLoss: '0',
            cryptoTax: '0',
            totalTax: $calc->totalTaxDue()->__toString(),
            isCorrection: false,
        );

        return (new PIT38XMLGenerator())->generate($pit38Data);
    }

    /**
     * Validate the generated XML against the minimal structural XSD.
     * Fails immediately if the XSD schema is violated.
     */
    private function assertValidXsd(string $xml): void
    {
        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        libxml_use_internal_errors(true);
        $isValid = $dom->schemaValidate(self::XSD_PATH);
        $errors  = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        if (! $isValid) {
            $messages = array_map(
                static fn (\LibXMLError $e): string => sprintf('[line %d] %s', $e->line, trim($e->message)),
                $errors,
            );
            self::fail(
                'Generated XML does not conform to pit38_minimal.xsd:' . PHP_EOL
                . implode(PHP_EOL, $messages),
            );
        }

        self::assertTrue(true, 'XSD validation passed');
    }

    /**
     * Normalise line endings so that cross-platform snapshot comparisons
     * do not fail due to CR+LF vs LF differences.
     */
    private function normalise(string $xml): string
    {
        return str_replace("\r\n", "\n", rtrim($xml));
    }

    private function writeSnapshot(string $xml): void
    {
        $dir = dirname(self::SNAPSHOT_PATH);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(self::SNAPSHOT_PATH, $xml);
    }
}
