<?php

declare(strict_types=1);

namespace App\Billing\Domain\Service;

use App\Billing\Domain\ValueObject\UserTier;

/**
 * Determines the required billing tier based on usage metrics.
 *
 * Business rules:
 * - FREE: 1 broker, <=30 closed positions
 * - REQUIRES_STANDARD: >1 broker OR >30 positions
 * - REQUIRES_PRO: reserved for cross-year FIFO / prior-year losses (future)
 */
final class TierResolver
{
    private const int FREE_MAX_BROKERS = 1;

    private const int FREE_MAX_POSITIONS = 30;

    public function resolve(int $brokerCount, int $closedPositionCount): UserTier
    {
        if ($brokerCount <= self::FREE_MAX_BROKERS && $closedPositionCount <= self::FREE_MAX_POSITIONS) {
            return UserTier::FREE;
        }

        return UserTier::REQUIRES_STANDARD;
    }
}
