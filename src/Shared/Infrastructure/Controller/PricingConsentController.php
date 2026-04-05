<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles the plan selection form submission from the pricing page.
 *
 * The form collects the art. 38 ust. 1 pkt 13 UPK withdrawal-right consent via
 * POST (not GET) so the consent value is captured in the request body and never
 * appears in URLs, server access logs, or browser history.
 */
final class PricingConsentController extends AbstractController
{
    private const array ALLOWED_PLANS = ['standard', 'pro'];

    #[Route('/cennik/wybierz', name: 'pricing_consent', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $plan = $request->request->getString('plan');
        $consent = $request->request->has('withdrawal_consent');

        if (! $consent || ! in_array($plan, self::ALLOWED_PLANS, strict: true)) {
            $this->addFlash('error', 'Prosimy zaakceptowac zgode przed kontynuowaniem.');

            return $this->redirectToRoute('pricing_index');
        }

        return $this->redirectToRoute('auth_login', ['plan' => $plan]);
    }
}
