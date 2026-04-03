<?php

declare(strict_types=1);

namespace App\Tests\Unit\Billing\Domain;

use App\Billing\Domain\Service\TierResolver;
use App\Billing\Domain\ValueObject\UserTier;
use PHPUnit\Framework\TestCase;

/**
 * Mutation-killing tests for TierResolver boundary conditions.
 * Targets: <= vs <, boundary at 30 positions and 1 broker.
 */
final class TierResolverMutationTest extends TestCase
{
    private TierResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new TierResolver();
    }

    /**
     * Kills boundary mutants: 29 positions with 1 broker is FREE.
     * If <= changed to <, 30 would fail but 29 would still pass, catching the mutant.
     */
    public function testFreeAt29PositionsOneBroker(): void
    {
        self::assertSame(UserTier::FREE, $this->resolver->resolve(brokerCount: 1, closedPositionCount: 29));
    }

    /**
     * Boundary: exactly 1 broker, exactly 30 positions = FREE.
     * This is the exact boundary -- already tested but reinforces the mutant kill.
     */
    public function testFreeAtExactBoundary(): void
    {
        self::assertSame(UserTier::FREE, $this->resolver->resolve(brokerCount: 1, closedPositionCount: 30));
    }

    /**
     * Boundary: 1 broker, 31 positions = REQUIRES_STANDARD.
     * Kills mutant where <= is changed to <.
     */
    public function testRequiresStandardAt31Positions(): void
    {
        self::assertSame(UserTier::REQUIRES_STANDARD, $this->resolver->resolve(brokerCount: 1, closedPositionCount: 31));
    }

    /**
     * Kills boundary mutant on broker count: 2 brokers with 0 positions.
     * Even with zero positions, multiple brokers require STANDARD.
     */
    public function testRequiresStandardWithTwoBrokersZeroPositions(): void
    {
        self::assertSame(UserTier::REQUIRES_STANDARD, $this->resolver->resolve(brokerCount: 2, closedPositionCount: 0));
    }

    /**
     * Kills AND/OR swap mutant: 2 brokers AND 31 positions.
     * If && changed to ||, one condition being true would be enough for FREE.
     */
    public function testRequiresStandardBothConditionsExceeded(): void
    {
        self::assertSame(UserTier::REQUIRES_STANDARD, $this->resolver->resolve(brokerCount: 2, closedPositionCount: 31));
    }

    /**
     * Kills mutant: 0 brokers, 31 positions.
     * Position limit alone triggers STANDARD, regardless of broker count.
     */
    public function testRequiresStandardZeroBrokersExcessPositions(): void
    {
        self::assertSame(UserTier::REQUIRES_STANDARD, $this->resolver->resolve(brokerCount: 0, closedPositionCount: 31));
    }
}
