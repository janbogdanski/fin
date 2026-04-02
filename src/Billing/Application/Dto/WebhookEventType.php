<?php

declare(strict_types=1);

namespace App\Billing\Application\Dto;

enum WebhookEventType: string
{
    case PAYMENT_COMPLETED = 'PAYMENT_COMPLETED';
    case OTHER = 'OTHER';
}
