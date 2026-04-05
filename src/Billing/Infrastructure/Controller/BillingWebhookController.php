<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Controller;

use App\Billing\Application\Command\HandlePaymentWebhook;
use App\Billing\Application\Command\HandlePaymentWebhookHandler;
use App\Billing\Application\Dto\WebhookEventType;
use App\Billing\Application\Port\PaymentGatewayPort;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles payment provider webhook callbacks.
 *
 * All payment provider specifics are behind PaymentGatewayPort.
 * To switch from Stripe to tpay/P24/PayU/BLIK -- only change the port adapter,
 * not this controller.
 */
final class BillingWebhookController extends AbstractController
{
    public function __construct(
        private readonly HandlePaymentWebhookHandler $webhookHandler,
        private readonly PaymentGatewayPort $paymentGateway,
        private readonly LoggerInterface $logger,
        private readonly RateLimiterFactory $billingWebhookLimiter,
    ) {
    }

    #[Route('/billing/webhook', name: 'billing_webhook', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $limiter = $this->billingWebhookLimiter->create($request->getClientIp() ?? 'unknown');

        if (! $limiter->consume()->isAccepted()) {
            return new JsonResponse([
                'error' => 'Too many requests',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $payload = $request->getContent();
        $event = $this->paymentGateway->verifyWebhook($payload, $request->headers->all());

        if ($event === null) {
            $this->logger->warning('Webhook signature verification failed.', [
                'ip' => $request->getClientIp(),
            ]);

            return new JsonResponse([
                'error' => 'Invalid signature',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($event->type === WebhookEventType::PAYMENT_COMPLETED && $event->sessionId !== '') {
            ($this->webhookHandler)(new HandlePaymentWebhook(
                providerSessionId: $event->sessionId,
                providerTransactionId: $event->transactionId,
            ));
        }

        return new JsonResponse([
            'status' => 'ok',
        ]);
    }
}
