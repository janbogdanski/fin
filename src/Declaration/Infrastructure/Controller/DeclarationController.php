<?php

declare(strict_types=1);

namespace App\Declaration\Infrastructure\Controller;

use App\Declaration\Application\DeclarationService;
use App\Declaration\Application\Result\NoData;
use App\Declaration\Application\Result\PaymentRequired;
use App\Declaration\Application\Result\PIT38WithSummary;
use App\Declaration\Application\Result\ProfileIncomplete;
use App\Declaration\Domain\DTO\PITZGData;
use App\Declaration\Domain\Service\AuditReportGenerator;
use App\Declaration\Domain\Service\PIT38XMLGenerator;
use App\Declaration\Domain\Service\PITZGGenerator;
use App\Declaration\Infrastructure\Pdf\DompdfPdfRenderer;
use App\Declaration\Infrastructure\Service\AuditReportDataBuilder;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/declaration')]
final class DeclarationController extends AbstractController
{
    public function __construct(
        private readonly DeclarationService $declarationService,
        private readonly PIT38XMLGenerator $xmlGenerator,
        private readonly PITZGGenerator $pitzgGenerator,
        private readonly AuditReportDataBuilder $auditReportDataBuilder,
        private readonly AuditReportGenerator $auditReportGenerator,
        private readonly DompdfPdfRenderer $pdfRenderer,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/{taxYear}/preview', name: 'declaration_preview', methods: ['GET'], requirements: [
        'taxYear' => '\d{4}',
    ])]
    public function preview(int $taxYear): Response
    {
        $userId = $this->resolveUserId();
        $result = $this->declarationService->buildPreview($userId, $taxYear);

        if ($result instanceof NoData) {
            $this->addFlash('warning', 'Brak danych -- wgraj CSV z transakcjami aby zobaczyc podglad PIT-38.');

            return $this->redirectToRoute('import_index');
        }

        /** @var PIT38WithSummary $result */
        $foreignDividends = array_filter(
            $result->summary->dividendsByCountry,
            static fn ($d) => $d->countryCode !== 'PL',
        );

        return $this->render('declaration/preview.html.twig', [
            'pit38' => $result->pit38,
            'foreignDividends' => $foreignDividends,
        ]);
    }

    #[Route('/{taxYear}/export/xml', name: 'declaration_export_xml', methods: ['GET'], requirements: [
        'taxYear' => '\d{4}',
    ])]
    public function exportXml(int $taxYear): Response
    {
        $userId = $this->resolveUserId();
        $result = $this->declarationService->buildPIT38ForExport($userId, $taxYear);

        $redirectResponse = $this->handleDeclarationResult($result, $taxYear, 'PIT-38');

        if ($redirectResponse !== null) {
            return $redirectResponse;
        }

        /** @var PIT38WithSummary $result */
        $xmlContent = $this->xmlGenerator->generate($result->pit38);

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
        $userId = $this->resolveUserId();

        $gateResult = $this->declarationService->checkValueGate($userId, $taxYear);

        if ($gateResult !== null) {
            return $this->redirectToRoute('billing_checkout_page', [
                'product_code' => $gateResult->requiredProduct->value,
            ]);
        }

        if (! $this->declarationService->hasTransactions($userId)) {
            $this->addFlash('info', 'Brak danych -- wgraj CSV z transakcjami aby wygenerowac raport PDF.');

            return $this->redirectToRoute('import_index');
        }

        $profile = $this->declarationService->resolveUserProfile($userId);

        $reportData = $this->auditReportDataBuilder->build(
            $userId,
            TaxYear::of($taxYear),
            $profile->firstName,
            $profile->lastName,
        );

        $html = $this->auditReportGenerator->generate($reportData, $this->clock->now());
        $pdfContent = $this->pdfRenderer->render($html);

        $response = new Response($pdfContent);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', sprintf(
            'attachment; filename="TaxPilot_Audit_%d.pdf"',
            $taxYear,
        ));

        return $response;
    }

    #[Route('/{taxYear}/pitzg/{countryCode}', name: 'declaration_pitzg', methods: ['GET'], requirements: [
        'taxYear' => '\d{4}',
        'countryCode' => '[A-Z]{2}',
    ])]
    public function pitzg(int $taxYear, string $countryCode): Response
    {
        $userId = $this->resolveUserId();

        $gateResult = $this->declarationService->checkValueGate($userId, $taxYear);

        if ($gateResult !== null) {
            return $this->redirectToRoute('billing_checkout_page', [
                'product_code' => $gateResult->requiredProduct->value,
            ]);
        }

        $result = $this->declarationService->buildPreview($userId, $taxYear);

        if ($result instanceof NoData) {
            $this->addFlash('warning', 'Brak danych -- wgraj CSV z transakcjami aby wygenerowac PIT/ZG.');

            return $this->redirectToRoute('import_index');
        }

        /** @var PIT38WithSummary $result */
        $pit38 = $result->pit38;

        if (! $pit38->hasCompletePersonalData()) {
            $this->addFlash('warning', 'Uzupelnij swoj NIP i dane osobowe w profilu, aby wygenerowac PIT/ZG.');

            return $this->redirectToRoute('profile_edit');
        }

        $country = CountryCode::fromString($countryCode);
        $dividendData = $result->summary->dividendsByCountry[$country->value] ?? null;

        if ($dividendData === null) {
            $this->addFlash('warning', sprintf('Brak dywidend z kraju %s.', $countryCode));

            return $this->redirectToRoute('declaration_preview', [
                'taxYear' => $taxYear,
            ]);
        }

        /** @var string $nip guaranteed by hasCompletePersonalData() check above */
        $nip = $pit38->nip;
        /** @var string $firstName */
        $firstName = $pit38->firstName;
        /** @var string $lastName */
        $lastName = $pit38->lastName;

        $pitzgData = new PITZGData(
            taxYear: $taxYear,
            nip: $nip,
            firstName: $firstName,
            lastName: $lastName,
            countryCode: $country,
            incomeGross: $dividendData->grossDividendPLN,
            taxPaidAbroad: $dividendData->whtPaidPLN,
            isCorrection: false,
        );

        $xmlContent = $this->pitzgGenerator->generate($pitzgData);

        $response = new Response($xmlContent);
        $response->headers->set('Content-Type', 'application/xml');
        $response->headers->set('Content-Disposition', sprintf(
            'attachment; filename="PIT-ZG_%d_%s.xml"',
            $taxYear,
            $countryCode,
        ));

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

    private function resolveUserId(): UserId
    {
        /** @var SecurityUser $user */
        $user = $this->getUser();

        return UserId::fromString($user->id());
    }
}
