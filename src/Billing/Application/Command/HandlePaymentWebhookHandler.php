<?php

declare(strict_types=1);

namespace App\Billing\Application\Command;

use App\Billing\Application\Port\PaymentRepositoryPort;
use Psr\Log\LoggerInterface;

final readonly class HandlePaymentWebhookHandler
{
    public function __construct(
        private PaymentRepositoryPort $paymentRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(HandlePaymentWebhook $command): void
    {
        $payment = $this->paymentRepository->findByProviderSessionId($command->providerSessionId);

        if ($payment === null) {
            $this->logger->warning('Webhook received for unknown session.', [
                'providerSessionId' => $command->providerSessionId,
            ]);

            return;
        }

        $payment->markAsPaid($command->providerTransactionId);
        $this->paymentRepository->save($payment);
        $this->paymentRepository->flush();

        $this->logger->info('Payment marked as paid.', [
            'paymentId' => $payment->id(),
            'providerSessionId' => $command->providerSessionId,
        ]);
    }
}
