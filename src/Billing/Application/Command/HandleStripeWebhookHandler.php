<?php

declare(strict_types=1);

namespace App\Billing\Application\Command;

use App\Billing\Application\Port\PaymentRepositoryPort;

final readonly class HandleStripeWebhookHandler
{
    public function __construct(
        private PaymentRepositoryPort $paymentRepository,
    ) {
    }

    public function __invoke(HandleStripeWebhook $command): void
    {
        $payment = $this->paymentRepository->findByStripeSessionId($command->stripeSessionId);

        if ($payment === null) {
            return;
        }

        $payment->markAsPaid($command->stripePaymentIntentId);
        $this->paymentRepository->save($payment);
        $this->paymentRepository->flush();
    }
}
