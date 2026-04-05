<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Controller;

use App\Billing\Application\Command\CreateCheckoutSession;
use App\Billing\Application\Command\CreateCheckoutSessionHandler;
use App\Billing\Domain\ValueObject\ProductCode;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\ValueObject\UserId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Initiates a payment checkout session.
 *
 * All payment provider specifics are behind PaymentGatewayPort.
 * To switch from Stripe to tpay/P24/PayU/BLIK -- only change the port adapter,
 * not this controller.
 */
final class BillingCheckoutController extends AbstractController
{
    public function __construct(
        private readonly CreateCheckoutSessionHandler $checkoutHandler,
    ) {
    }

    #[Route('/billing/checkout', name: 'billing_checkout', methods: ['POST'])]
    public function __invoke(Request $request): Response
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
}
