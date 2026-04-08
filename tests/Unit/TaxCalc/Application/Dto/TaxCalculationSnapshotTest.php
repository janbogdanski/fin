<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Application\Dto;

use App\TaxCalc\Application\Dto\TaxCalculationSnapshot;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that TaxCalculationSnapshot constructs correctly.
 *
 * The DTO is responsible for self-assigning a UUID v4 id and a generatedAt timestamp.
 * Both are set in the constructor — we verify they are present and correctly typed.
 */
final class TaxCalculationSnapshotTest extends TestCase
{
    public function testConstructorPopulatesAllFields(): void
    {
        $before = new \DateTimeImmutable();

        $snapshot = new TaxCalculationSnapshot(
            userId: 'a1b2c3d4-0000-0000-0000-000000000001',
            taxYear: 2024,
            equityGainLoss: '1500.00',
            equityTaxBase: '1500.00',
            equityTaxDue: '285.00',
            priorLossesApplied: '200.00',
            dividendIncome: '400.00',
            dividendTaxDue: '76.00',
            xmlSha256: str_repeat('a', 64),
        );

        $after = new \DateTimeImmutable();

        self::assertSame('a1b2c3d4-0000-0000-0000-000000000001', $snapshot->userId);
        self::assertSame(2024, $snapshot->taxYear);
        self::assertSame('1500.00', $snapshot->equityGainLoss);
        self::assertSame('1500.00', $snapshot->equityTaxBase);
        self::assertSame('285.00', $snapshot->equityTaxDue);
        self::assertSame('200.00', $snapshot->priorLossesApplied);
        self::assertSame('400.00', $snapshot->dividendIncome);
        self::assertSame('76.00', $snapshot->dividendTaxDue);
        self::assertSame(str_repeat('a', 64), $snapshot->xmlSha256);
    }

    public function testIdIsValidUuidV4(): void
    {
        $snapshot = new TaxCalculationSnapshot(
            userId: 'a1b2c3d4-0000-0000-0000-000000000001',
            taxYear: 2024,
            equityGainLoss: '0.00',
            equityTaxBase: '0.00',
            equityTaxDue: '0.00',
            priorLossesApplied: '0.00',
            dividendIncome: '0.00',
            dividendTaxDue: '0.00',
            xmlSha256: str_repeat('b', 64),
        );

        // UUID v4 pattern: xxxxxxxx-xxxx-4xxx-[89ab]xxx-xxxxxxxxxxxx
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $snapshot->id,
        );
    }

    public function testGeneratedAtIsSetToCurrentTime(): void
    {
        $before = new \DateTimeImmutable();

        $snapshot = new TaxCalculationSnapshot(
            userId: 'a1b2c3d4-0000-0000-0000-000000000001',
            taxYear: 2024,
            equityGainLoss: '0.00',
            equityTaxBase: '0.00',
            equityTaxDue: '0.00',
            priorLossesApplied: '0.00',
            dividendIncome: '0.00',
            dividendTaxDue: '0.00',
            xmlSha256: str_repeat('c', 64),
        );

        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before->getTimestamp(), $snapshot->generatedAt->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $snapshot->generatedAt->getTimestamp());
    }

    public function testTwoSnapshotsHaveDifferentIds(): void
    {
        $a = new TaxCalculationSnapshot(
            userId: 'a1b2c3d4-0000-0000-0000-000000000001',
            taxYear: 2024,
            equityGainLoss: '0.00',
            equityTaxBase: '0.00',
            equityTaxDue: '0.00',
            priorLossesApplied: '0.00',
            dividendIncome: '0.00',
            dividendTaxDue: '0.00',
            xmlSha256: str_repeat('d', 64),
        );

        $b = new TaxCalculationSnapshot(
            userId: 'a1b2c3d4-0000-0000-0000-000000000001',
            taxYear: 2024,
            equityGainLoss: '0.00',
            equityTaxBase: '0.00',
            equityTaxDue: '0.00',
            priorLossesApplied: '0.00',
            dividendIncome: '0.00',
            dividendTaxDue: '0.00',
            xmlSha256: str_repeat('d', 64),
        );

        self::assertNotSame($a->id, $b->id);
    }
}
