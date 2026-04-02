<?php

declare(strict_types=1);

namespace App\Billing\Domain\ValueObject;

enum PaymentStatus: string
{
    case PENDING = 'PENDING';
    case PAID = 'PAID';
    case FAILED = 'FAILED';
}
