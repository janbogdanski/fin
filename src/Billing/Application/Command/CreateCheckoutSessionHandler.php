<?php

declare(strict_types=1);

namespace App\Billing\Application\Command;

use App\Billing\Application\Port\PaymentGatewayPort;
use App\Billing\Application\Port\PaymentRepositoryPort;
use App\Billing\Domain\Model\Payment;

final readonly class CreateCheckoutSessionHandler
{
    public function __construct(
        private PaymentGatewayPort $paymentGateway,
        private PaymentRepositoryPort $paymentRepository,
    ) {
    }

    public function __invoke(CreateCheckoutSession $command): CreateCheckoutSessionResult
    {
        $session = $this->paymentGateway->createCheckoutSession(
            $command->userId,
            $command->productCode,
            $command->successUrl,
            $command->cancelUrl,
        );

        $payment = Payment::create(
            userId: $command->userId,
            providerSessionId: $session->sessionId,
            productCode: $command->productCode,
        );

        $this->paymentRepository->save($payment);
        $this->paymentRepository->flush();

        return new CreateCheckoutSessionResult($session->checkoutUrl);
    }
}
