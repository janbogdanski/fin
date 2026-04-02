<?php

declare(strict_types=1);

namespace App\Tests\Unit\Billing\Domain;

use App\Billing\Domain\Service\TierResolver;
use App\Billing\Domain\ValueObject\UserTier;
use PHPUnit\Framework\TestCase;

final class TierResolverTest extends TestCase
{
    private TierResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new TierResolver();
    }

    public function testFreeTierForOneBrokerAndThirtyPositions(): void
    {
        $tier = $this->resolver->resolve(brokerCount: 1, closedPositionCount: 30);

        self::assertSame(UserTier::FREE, $tier);
    }

    public function testFreeTierForZeroBrokersAndZeroPositions(): void
    {
        $tier = $this->resolver->resolve(brokerCount: 0, closedPositionCount: 0);

        self::assertSame(UserTier::FREE, $tier);
    }

    public function testRequiresStandardForTwoBrokers(): void
    {
        $tier = $this->resolver->resolve(brokerCount: 2, closedPositionCount: 10);

        self::assertSame(UserTier::REQUIRES_STANDARD, $tier);
    }

    public function testRequiresStandardForThirtyOnePositions(): void
    {
        $tier = $this->resolver->resolve(brokerCount: 1, closedPositionCount: 31);

        self::assertSame(UserTier::REQUIRES_STANDARD, $tier);
    }

    public function testRequiresStandardForMultipleBrokersAndManyPositions(): void
    {
        $tier = $this->resolver->resolve(brokerCount: 3, closedPositionCount: 100);

        self::assertSame(UserTier::REQUIRES_STANDARD, $tier);
    }

    public function testFreeTierBoundaryOneBrokerThirtyPositionsExactly(): void
    {
        $tier = $this->resolver->resolve(brokerCount: 1, closedPositionCount: 30);

        self::assertSame(UserTier::FREE, $tier);
    }
}
