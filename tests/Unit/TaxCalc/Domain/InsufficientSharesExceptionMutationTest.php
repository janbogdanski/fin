<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Domain;

use App\Shared\Domain\ValueObject\ISIN;
use App\TaxCalc\Domain\Exception\InsufficientSharesException;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

/**
 * Mutation-killing tests for InsufficientSharesException.
 *
 * Targets: MethodCallRemoval of parent::__construct.
 */
final class InsufficientSharesExceptionMutationTest extends TestCase
{
    /**
     * Kills MethodCallRemoval: parent::__construct() removed would make getMessage() empty.
     */
    public function testExceptionMessageContainsIsinAndQuantity(): void
    {
        $isin = ISIN::fromString('US0378331005');
        $e = new InsufficientSharesException($isin, BigDecimal::of('15'));

        $msg = $e->getMessage();

        self::assertStringContainsString('US0378331005', $msg);
        self::assertStringContainsString('15', $msg);
        self::assertStringContainsString('Insufficient shares', $msg);
    }
}
