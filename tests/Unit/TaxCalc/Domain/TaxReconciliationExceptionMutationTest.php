<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Domain;

use App\TaxCalc\Domain\Exception\TaxReconciliationException;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

/**
 * Mutation-killing tests for TaxReconciliationException.
 *
 * Targets: Concat, ConcatOperandRemoval on the parent::__construct message.
 */
final class TaxReconciliationExceptionMutationTest extends TestCase
{
    /**
     * Kills Concat and ConcatOperandRemoval mutants:
     * The exception message must contain the basket name, path A value, and path B value.
     */
    public function testExceptionMessageContainsAllComponents(): void
    {
        $e = new TaxReconciliationException(
            basket: 'equity',
            pathA: BigDecimal::of('1234.56'),
            pathB: BigDecimal::of('1234.00'),
        );

        $msg = $e->getMessage();

        self::assertStringContainsString('equity', $msg);
        self::assertStringContainsString('1234.56', $msg);
        self::assertStringContainsString('1234.00', $msg);
        self::assertStringContainsString('path A', $msg);
        self::assertStringContainsString('path B', $msg);
        self::assertStringContainsString('Reconciliation failed', $msg);
    }

    public function testPublicPropertiesAreAccessible(): void
    {
        $e = new TaxReconciliationException(
            basket: 'crypto',
            pathA: BigDecimal::of('999.99'),
            pathB: BigDecimal::of('1000.01'),
        );

        self::assertSame('crypto', $e->basket);
        self::assertTrue($e->pathA->isEqualTo('999.99'));
        self::assertTrue($e->pathB->isEqualTo('1000.01'));
    }
}
