<?php

declare(strict_types=1);

namespace App\Declaration\Infrastructure\Controller;

use App\Billing\Application\Port\PaymentRepositoryPort;
use App\Billing\Domain\Service\TierResolver;
use App\Billing\Domain\ValueObject\ProductCode;
use App\Billing\Domain\ValueObject\UserTier;
use App\Declaration\Domain\DTO\PIT38Data;
use App\Declaration\Domain\Service\PIT38XMLGenerator;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\ValueObject\UserId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/declaration')]
final class DeclarationController extends AbstractController
{
    public function __construct(
        private readonly PIT38XMLGenerator $xmlGenerator,
        private readonly TierResolver $tierResolver,
        private readonly PaymentRepositoryPort $paymentRepository,
    ) {
    }

    #[Route('/{taxYear}/preview', name: 'declaration_preview', methods: ['GET'], requirements: [
        'taxYear' => '\d{4}',
    ])]
    public function preview(int $taxYear): Response
    {
        // TODO: wire to real data via ports (GetPIT38DataQuery)
        $pit38 = $this->getDemoPIT38Data($taxYear);
        $this->addFlash('info', 'Tryb demo — wyswietlane sa przykladowe dane.');

        return $this->render('declaration/preview.html.twig', [
            'pit38' => $pit38,
        ]);
    }

    #[Route('/{taxYear}/export/xml', name: 'declaration_export_xml', methods: ['GET'], requirements: [
        'taxYear' => '\d{4}',
    ])]
    public function exportXml(int $taxYear): Response
    {
        $gateResult = $this->checkValueGate();

        if ($gateResult !== null) {
            return $gateResult;
        }

        // TODO: wire to real data via ports (GetPIT38DataQuery)
        $pit38 = $this->getDemoPIT38Data($taxYear);
        $xmlContent = $this->xmlGenerator->generate($pit38);

        $response = new Response($xmlContent);
        $response->headers->set('Content-Type', 'application/xml');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="PIT-38_%d.xml"', $taxYear));

        return $response;
    }

    #[Route('/{taxYear}/export/pdf', name: 'declaration_export_pdf', methods: ['GET'], requirements: [
        'taxYear' => '\d{4}',
    ])]
    public function exportPdf(int $taxYear): Response
    {
        // TODO: wire to real PDF generation service
        $this->addFlash('info', 'Generowanie PDF audit trail jest w przygotowaniu.');

        return $this->redirectToRoute('declaration_preview', [
            'taxYear' => $taxYear,
        ]);
    }

    #[Route('/{taxYear}/pitzg/{countryCode}', name: 'declaration_pitzg', methods: ['GET'], requirements: [
        'taxYear' => '\d{4}',
        'countryCode' => '[A-Z]{2}',
    ])]
    public function pitzg(int $taxYear, string $countryCode): Response
    {
        // TODO: wire to real data via ports (GetDividendByCountryQuery)
        $this->addFlash('info', 'Tryb demo — brak danych PIT/ZG.');

        return $this->redirectToRoute('declaration_preview', [
            'taxYear' => $taxYear,
        ]);
    }

    /**
     * Value gate: checks if the user's usage requires a paid tier,
     * and if so, whether they have a valid payment.
     *
     * Returns null if access is allowed, or a redirect Response to billing checkout.
     */
    private function checkValueGate(): ?Response
    {
        /** @var SecurityUser $securityUser */
        $securityUser = $this->getUser();
        $userId = UserId::fromString($securityUser->id());

        // TODO: wire to real broker/position counts from TaxCalc
        // For now, demo mode uses free-tier values
        $brokerCount = 1;
        $closedPositionCount = 0;

        $tier = $this->tierResolver->resolve($brokerCount, $closedPositionCount);

        if ($tier === UserTier::FREE) {
            return null;
        }

        $requiredProduct = $tier === UserTier::REQUIRES_PRO
            ? ProductCode::PRO
            : ProductCode::STANDARD;

        if ($this->paymentRepository->hasActivePaymentForTier($userId, $requiredProduct)) {
            return null;
        }

        return $this->redirectToRoute('billing_checkout_page', [
            'product_code' => $requiredProduct->value,
        ]);
    }

    /**
     * Placeholder PIT-38 data with zeroed values for demo mode.
     * TODO: wire to real data via ports — remove this method when persistence is ready.
     */
    private function getDemoPIT38Data(int $taxYear): PIT38Data
    {
        return new PIT38Data(
            taxYear: $taxYear,
            nip: '5260000005',
            firstName: 'Demo',
            lastName: 'Uzytkownik',
            equityProceeds: '0.00',
            equityCosts: '0.00',
            equityIncome: '0.00',
            equityLoss: '0.00',
            equityTaxBase: '0.00',
            equityTax: '0.00',
            dividendGross: '0.00',
            dividendWHT: '0.00',
            dividendTaxDue: '0.00',
            cryptoProceeds: '0.00',
            cryptoCosts: '0.00',
            cryptoIncome: '0.00',
            cryptoLoss: '0.00',
            cryptoTax: '0.00',
            totalTax: '0.00',
            isCorrection: false,
        );
    }
}
