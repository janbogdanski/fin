<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Controller;

use App\Billing\Application\Command\CreateCheckoutSession;
use App\Billing\Application\Command\CreateCheckoutSessionHandler;
use App\Billing\Application\Command\HandleStripeWebhook;
use App\Billing\Application\Command\HandleStripeWebhookHandler;
use App\Billing\Application\Port\PaymentGatewayPort;
use App\Billing\Domain\ValueObject\ProductCode;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\ValueObject\UserId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Billing controller — provider-agnostic.
 *
 * All payment provider specifics are behind PaymentGatewayPort.
 * To switch from Stripe to tpay/P24/PayU/BLIK — only change the port adapter,
 * not this controller.
 */
#[Route('/billing')]
final class BillingController extends AbstractController
{
    public function __construct(
        private readonly CreateCheckoutSessionHandler $checkoutHandler,
        private readonly HandleStripeWebhookHandler $webhookHandler,
        private readonly PaymentGatewayPort $paymentGateway,
    ) {
    }

    #[Route('/checkout', name: 'billing_checkout', methods: ['POST'])]
    public function checkout(Request $request): Response
    {
        /** @var SecurityUser $securityUser */
        $securityUser = $this->getUser();
        $productCodeValue = $request->request->getString('product_code', 'STANDARD');
        $productCode = ProductCode::tryFrom($productCodeValue);

        if ($productCode === null) {
            throw $this->createNotFoundException('Invalid product code.');
        }

        $result = ($this->checkoutHandler)(new CreateCheckoutSession(
            userId: UserId::fromString($securityUser->id()),
            productCode: $productCode,
            successUrl: $this->generateUrl('declaration_preview', [
                'taxYear' => date('Y'),
            ], UrlGeneratorInterface::ABSOLUTE_URL) . '?payment=success',
            cancelUrl: $this->generateUrl('declaration_preview', [
                'taxYear' => date('Y'),
            ], UrlGeneratorInterface::ABSOLUTE_URL) . '?payment=cancelled',
        ));

        return $this->redirect($result->checkoutUrl);
    }

    #[Route('/webhook', name: 'billing_webhook', methods: ['POST'])]
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = (string) $request->headers->get('Stripe-Signature', '');

        $event = $this->paymentGateway->verifyWebhook($payload, $signature);

        if ($event === null) {
            return new JsonResponse([
                'error' => 'Invalid signature',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($event['type'] === 'checkout.session.completed' && $event['sessionId'] !== '') {
            ($this->webhookHandler)(new HandleStripeWebhook(
                stripeSessionId: $event['sessionId'],
                stripePaymentIntentId: $event['paymentIntentId'],
            ));
        }

        return new JsonResponse([
            'status' => 'ok',
        ]);
    }
}
