<?php

declare(strict_types=1);

namespace App\Declaration\Infrastructure\Controller;

use App\Declaration\Application\DeclarationService;
use App\Declaration\Application\Result\NoData;
use App\Declaration\Application\Result\PaymentRequired;
use App\Declaration\Application\Result\PIT38WithSummary;
use App\Declaration\Application\Result\ProfileIncomplete;
use App\Declaration\Domain\Service\PIT38XMLGenerator;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Dto\TaxCalculationSnapshot;
use App\TaxCalc\Application\Port\TaxCalculationSnapshotPort;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DeclarationExportXmlController extends AbstractController
{
    public function __construct(
        private readonly DeclarationService $declarationService,
        private readonly PIT38XMLGenerator $xmlGenerator,
        private readonly TaxCalculationSnapshotPort $snapshotRepository,
    ) {
    }

    #[Route('/declaration/{taxYear}/export/xml', name: 'declaration_export_xml', methods: ['GET'], requirements: [
        'taxYear' => '\d{4}',
    ])]
    public function __invoke(int $taxYear): Response
    {
        /** @var SecurityUser $user */
        $user = $this->getUser();
        $userId = UserId::fromString($user->id());

        $result = $this->declarationService->buildPIT38ForExport($userId, $taxYear);

        $redirectResponse = $this->handleDeclarationResult($result, $taxYear, 'PIT-38');

        if ($redirectResponse !== null) {
            return $redirectResponse;
        }

        /** @var PIT38WithSummary $result */
        $xmlContent = $this->xmlGenerator->generate($result->pit38);

        $dividendGross = '0.00';
        foreach ($result->summary->dividendsByCountry as $country) {
            $dividendGross = bcadd($dividendGross, $country->grossDividendPLN, 2);
        }

        $snapshot = new TaxCalculationSnapshot(
            userId: $userId->toString(),
            taxYear: $taxYear,
            equityGainLoss: $result->summary->equityGainLoss,
            equityTaxBase: $result->summary->equityTaxableIncome,
            equityTaxDue: $result->summary->equityTax,
            priorLossesApplied: $result->summary->equityLossDeduction,
            dividendIncome: $dividendGross,
            dividendTaxDue: $result->summary->dividendTotalTaxDue,
            xmlSha256: hash('sha256', $xmlContent),
        );
        $this->snapshotRepository->save($snapshot);

        $response = new Response($xmlContent);
        $response->headers->set('Content-Type', 'application/xml');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="PIT-38_%d.xml"', $taxYear));

        return $response;
    }

    /**
     * Maps non-success DeclarationResult to a redirect Response, or null if result is PIT38WithSummary.
     */
    private function handleDeclarationResult(
        \App\Declaration\Application\Result\DeclarationResult $result,
        int $taxYear,
        string $formLabel,
    ): ?Response {
        if ($result instanceof PaymentRequired) {
            return $this->redirectToRoute('billing_checkout_page', [
                'product_code' => $result->requiredProduct->value,
            ]);
        }

        if ($result instanceof NoData) {
            $this->addFlash('warning', sprintf(
                'Brak danych -- wgraj CSV z transakcjami aby wygenerowac %s.',
                $formLabel,
            ));

            return $this->redirectToRoute('import_index');
        }

        if ($result instanceof ProfileIncomplete) {
            $this->addFlash('warning', sprintf(
                'Uzupelnij swoj NIP i dane osobowe w profilu, aby wygenerowac %s.',
                $formLabel,
            ));

            return $this->redirectToRoute('profile_edit');
        }

        return null;
    }
}
