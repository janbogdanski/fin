<?php

declare(strict_types=1);

namespace App\Declaration\Infrastructure\Controller;

use App\Declaration\Application\DeclarationService;
use App\Declaration\Domain\Service\AuditReportGenerator;
use App\Declaration\Infrastructure\Pdf\DompdfPdfRenderer;
use App\Declaration\Infrastructure\Service\AuditReportDataBuilder;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DeclarationExportPdfController extends AbstractController
{
    public function __construct(
        private readonly DeclarationService $declarationService,
        private readonly AuditReportDataBuilder $auditReportDataBuilder,
        private readonly AuditReportGenerator $auditReportGenerator,
        private readonly DompdfPdfRenderer $pdfRenderer,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/declaration/{taxYear}/export/pdf', name: 'declaration_export_pdf', methods: ['GET'], requirements: [
        'taxYear' => '\d{4}',
    ])]
    public function __invoke(int $taxYear): Response
    {
        /** @var SecurityUser $user */
        $user = $this->getUser();
        $userId = UserId::fromString($user->id());

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
}
