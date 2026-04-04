<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Controller;

use App\Billing\Application\Command\CreateCheckoutSession;
use App\Billing\Application\Command\CreateCheckoutSessionHandler;
use App\Billing\Application\Command\HandlePaymentWebhook;
use App\Billing\Application\Command\HandlePaymentWebhookHandler;
use App\Billing\Application\Dto\WebhookEventType;
use App\Billing\Application\Port\PaymentGatewayPort;
use App\Billing\Domain\ValueObject\ProductCode;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\ValueObject\UserId;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Billing controller -- provider-agnostic.
 *
 * All payment provider specifics are behind PaymentGatewayPort.
 * To switch from Stripe to tpay/P24/PayU/BLIK -- only change the port adapter,
 * not this controller.
 */
#[Route('/billing')]
final class BillingController extends AbstractController
{
    public function __construct(
        private readonly CreateCheckoutSessionHandler $checkoutHandler,
        private readonly HandlePaymentWebhookHandler $webhookHandler,
        private readonly PaymentGatewayPort $paymentGateway,
        private readonly LoggerInterface $logger,
        private readonly RateLimiterFactory $billingWebhookLimiter,
    ) {
    }

    #[Route('/checkout', name: 'billing_checkout', methods: ['POST'])]
    public function checkout(Request $request): Response
    {
        if (! $this->isCsrfTokenValid('billing_checkout', (string) $request->request->get('_csrf_token', ''))) {
            $this->addFlash('error', 'Nieprawidlowy token CSRF. Sprobuj ponownie.');

            return $this->redirectToRoute('dashboard_index');
        }

        /** @var SecurityUser $securityUser */
        $securityUser = $this->getUser();
        $productCodeValue = $request->request->getString('product_code', 'STANDARD');
        $productCode = ProductCode::tryFrom($productCodeValue);

        if ($productCode === null) {
            throw $this->createNotFoundException('Invalid product code.');
        }

        $taxYear = $request->request->getInt('tax_year');

        if ($taxYear < 2020 || $taxYear > 2100) {
            throw $this->createNotFoundException('Invalid tax year.');
        }

        $result = ($this->checkoutHandler)(new CreateCheckoutSession(
            userId: UserId::fromString($securityUser->id()),
            productCode: $productCode,
            successUrl: $this->generateUrl('declaration_preview', [
                'taxYear' => $taxYear,
            ], UrlGeneratorInterface::ABSOLUTE_URL) . '?payment=success',
            cancelUrl: $this->generateUrl('declaration_preview', [
                'taxYear' => $taxYear,
            ], UrlGeneratorInterface::ABSOLUTE_URL) . '?payment=cancelled',
        ));

        return $this->redirect($result->checkoutUrl);
    }

    #[Route('/webhook', name: 'billing_webhook', methods: ['POST'])]
    public function webhook(Request $request): JsonResponse
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
