<?php

declare(strict_types=1);

namespace App\Tests\GoldenDataset\Xml;

use App\Declaration\Domain\DTO\PIT38Data;
use App\Declaration\Domain\Service\PIT38XMLGenerator;
use App\Tests\GoldenDataset\Concern\AssertsPIT38XmlValid;
use PHPUnit\Framework\TestCase;

/**
 * Golden XML snapshot tests for PIT38XMLGenerator.
 *
 * Each test builds a PIT38Data DTO with known, fixed values, generates the XML
 * and compares it byte-for-byte against a committed snapshot file stored in
 * tests/GoldenDataset/Xml/fixtures/.
 *
 * First-run behaviour: if the snapshot file does not exist yet, it is created
 * and the test passes with a warning (bootstrapping run). On every subsequent
 * run the output must match the snapshot exactly (after LF normalisation).
 *
 * To intentionally update a snapshot after a generator change:
 *   1. Delete the relevant file in tests/GoldenDataset/Xml/fixtures/
 *   2. Run this test once to regenerate it
 *   3. Review the diff and commit the updated file
 *
 * @group snapshot
 */
final class PIT38XmlSnapshotTest extends TestCase
{
    use AssertsPIT38XmlValid;

    private const FIXTURES_DIR = __DIR__ . '/fixtures';

    private PIT38XMLGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new PIT38XMLGenerator();
    }

    /**
     * Scenario 1: equity-only-gain
     *
     * Equity gain with no dividends and no crypto.
     * P_22 > P_23 → income in P_24, zero in P_25, positive tax base and tax.
     */
    public function testEquityOnlyGainMatchesSnapshot(): void
    {
        $data = new PIT38Data(
            taxYear: 2025,
            nip: '5260000005',
            firstName: 'Anna',
            lastName: 'Nowak',
            equityProceeds: '50000.00',
            equityCosts: '38000.00',
            equityIncome: '12000.00',
            equityLoss: '0',
            equityTaxBase: '12000',
            equityTax: '2280',
            dividendGross: '0',
            dividendWHT: '0',
            dividendTaxDue: '0',
            cryptoProceeds: '0',
            cryptoCosts: '0',
            cryptoIncome: '0',
            cryptoLoss: '0',
            cryptoTax: '0',
            totalTax: '2280',
            isCorrection: false,
        );

        $this->assertSnapshotMatches('equity-only-gain.xml', $data);
    }

    /**
     * Scenario 2: full-pit38
     *
     * Equity + foreign dividends + crypto all populated.
     * NIP 5261040828 — Ministerstwo Finansow (real, valid NIP).
     */
    public function testFullPit38MatchesSnapshot(): void
    {
        $data = new PIT38Data(
            taxYear: 2025,
            nip: '5261040828',
            firstName: 'Piotr',
            lastName: 'Wisniewski',
            equityProceeds: '120000.00',
            equityCosts: '95000.00',
            equityIncome: '25000.00',
            equityLoss: '0',
            equityTaxBase: '25000',
            equityTax: '4750',
            dividendGross: '3000.00',
            dividendWHT: '450.00',
            dividendTaxDue: '120',
            cryptoProceeds: '18000.00',
            cryptoCosts: '12000.00',
            cryptoIncome: '6000.00',
            cryptoLoss: '0',
            cryptoTax: '1140',
            totalTax: '6010',
            isCorrection: false,
        );

        $this->assertSnapshotMatches('full-pit38.xml', $data);
    }

    /**
     * Scenario 3: equity-loss
     *
     * Equity loss (P_23 > P_22) combined with crypto loss.
     * P_24 = 0, P_25 has the loss amount; tax base and taxes are all zero.
     */
    public function testEquityLossMatchesSnapshot(): void
    {
        $data = new PIT38Data(
            taxYear: 2025,
            nip: '5260000005',
            firstName: 'Marek',
            lastName: 'Zielinski',
            equityProceeds: '20000.00',
            equityCosts: '28000.00',
            equityIncome: '0',
            equityLoss: '8000.00',
            equityTaxBase: '0',
            equityTax: '0',
            dividendGross: '0',
            dividendWHT: '0',
            dividendTaxDue: '0',
            cryptoProceeds: '5000.00',
            cryptoCosts: '9000.00',
            cryptoIncome: '0',
            cryptoLoss: '4000.00',
            cryptoTax: '0',
            totalTax: '0',
            isCorrection: false,
        );

        $this->assertSnapshotMatches('equity-loss.xml', $data);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function assertSnapshotMatches(string $filename, PIT38Data $data): void
    {
        $xml = $this->generator->generate($data);

        $this->assertPIT38XmlValidatesAgainstSchema($xml);

        $snapshotPath = self::FIXTURES_DIR . '/' . $filename;

        if (! file_exists($snapshotPath)) {
            $this->writeSnapshot($snapshotPath, $xml);
            fwrite(
                STDERR,
                PHP_EOL . '[snapshot] Created: ' . $snapshotPath
                . ' — review and commit to lock-in expected output.' . PHP_EOL,
            );
            self::assertTrue(true, 'First-run: snapshot written to ' . $snapshotPath);

            return;
        }

        $snapshot = $this->normalise((string) file_get_contents($snapshotPath));
        $actual = $this->normalise($xml);

        self::assertSame(
            $snapshot,
            $actual,
            sprintf(
                'PIT-38 XML output does not match snapshot "%s". '
                . 'If the change is intentional, delete the snapshot file and re-run to regenerate.',
                $filename,
            ),
        );
    }

    /**
     * Normalise line endings so cross-platform snapshot comparisons
     * do not fail due to CR+LF vs LF differences.
     */
    private function normalise(string $xml): string
    {
        return str_replace("\r\n", "\n", rtrim($xml));
    }

    private function writeSnapshot(string $path, string $xml): void
    {
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $xml);
    }
}
