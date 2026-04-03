<?php

declare(strict_types=1);

namespace App\Identity\Application\Command;

final readonly class ApplyReferralCode
{
    public function __construct(
        public string $refereeUserId,
        public string $referralCode,
    ) {
    }
}
