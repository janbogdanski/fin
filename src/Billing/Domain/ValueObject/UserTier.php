<?php

declare(strict_types=1);

namespace App\Billing\Domain\ValueObject;

enum UserTier: string
{
    case FREE = 'FREE';
    case REQUIRES_STANDARD = 'REQUIRES_STANDARD';
    case REQUIRES_PRO = 'REQUIRES_PRO';
}
