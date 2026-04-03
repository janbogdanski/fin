<?php

declare(strict_types=1);

namespace App\Declaration\Infrastructure\Controller;

use App\Billing\Application\Port\PaymentRepositoryPort;
use App\Billing\Domain\Service\TierResolver;
use App\Billing\Domain\ValueObject\ProductCode;
use App\Billing\Domain\ValueObject\UserTier;
use App\BrokerImport\Application\Port\ImportedTransactionRepositoryInterface;
use App\Declaration\Domain\DTO\PIT38Data;
use App\Declaration\Domain\DTO\PITZGData;
use App\Declaration\Domain\Service\AuditReportGenerator;
use App\Declaration\Domain\Service\PIT38XMLGenerator;
use App\Declaration\Domain\Service\PITZGGenerator;
use App\Declaration\Infrastructure\Pdf\DompdfPdfRenderer;
use App\Declaration\Infrastructure\Service\AuditReportDataBuilder;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Query\GetTaxSummary;
use App\TaxCalc\Application\Query\GetTaxSummaryHandler;
use App\TaxCalc\Application\Query\TaxSummaryResult;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/declaration')]
final class DeclarationController extends AbstractController
{
    public function __construct(
        private readonly PIT38XMLGenerator $xmlGenerator,
        private readonly PITZGGenerator $pitzgGenerator,
        private readonly TierResolver $tierResolver,
        private readonly PaymentRepositoryPort $paymentRepository,
        private readonly ImportedTransactionRepositoryInterface $importedTxRepo,
        private readonly GetTaxSummaryHandler $taxSummaryHandler,
        private readonly UserRepositoryInterface $userRepository,
        private readonly AuditReportDataBuilder $auditReportDataBuilder,
        private readonly AuditReportGenerator $auditReportGenerator,
        private readonly DompdfPdfRenderer $pdfRenderer,
    ) {
    }

    #[Route('/{taxYear}/preview', name: 'declaration_preview', methods: ['GET'], requirements: [
        'taxYear' => '\d{4}',
    ])]
    public function preview(int $taxYear): Response
    {
        $result = $this->buildPIT38WithSummary($taxYear);

        if ($result === null) {
            $this->addFlash('warning', 'Brak danych -- wgraj CSV z transakcjami aby zobaczyc podglad PIT-38.');

            return $this->redirectToRoute('import_index');
        }

        $foreignDividends = array_filter(
            $result['summary']->dividendsByCountry,
            static fn ($d) => $d->countryCode !== 'PL',
        );

        return $this->render('declaration/preview.html.twig', [
            'pit38' => $result['pit38'],
            'foreignDividends' => $foreignDividends,
        ]);
    }

    #[Route('/{taxYear}/export/xml', name: 'declaration_export_xml', methods: ['GET'], requirements: [
        'taxYear' => '\d{4}',
    ])]
    public function exportXml(int $taxYear): Response
    {
        $gateResult = $this->checkValueGate($taxYear);

        if ($gateResult !== null) {
            return $gateResult;
        }

        $pit38 = $this->buildPIT38Data($taxYear);

        if ($pit38 === null) {
            $this->addFlash('warning', 'Brak danych -- wgraj CSV z transakcjami aby wygenerowac PIT-38.');

            return $this->redirectToRoute('import_index');
        }

        if (! $pit38->hasCompletePersonalData()) {
            $this->addFlash('warning', 'Uzupelnij swoj NIP i dane osobowe w profilu, aby wygenerowac PIT-38.');

            return $this->redirectToRoute('profile_edit');
        }

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
        $gateResult = $this->checkValueGate($taxYear);

        if ($gateResult !== null) {
            return $gateResult;
        }

        $userId = $this->resolveUserId();

        if ($this->importedTxRepo->countByUser($userId) === 0) {
            $this->addFlash('info', 'Brak danych -- wgraj CSV z transakcjami aby wygenerowac raport PDF.');

            return $this->redirectToRoute('import_index');
        }

        $user = $this->userRepository->findById($userId);
        $firstName = $user?->firstName() ?? '';
        $lastName = $user?->lastName() ?? '';

        $reportData = $this->auditReportDataBuilder->build(
            $userId,
            TaxYear::of($taxYear),
            $firstName,
            $lastName,
        );

        $html = $this->auditReportGenerator->generate($reportData);
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
        $gateResult = $this->checkValueGate($taxYear);

        if ($gateResult !== null) {
            return $gateResult;
        }

        $result = $this->buildPIT38WithSummary($taxYear);

        if ($result === null) {
            $this->addFlash('warning', 'Brak danych -- wgraj CSV z transakcjami aby wygenerowac PIT/ZG.');

            return $this->redirectToRoute('import_index');
        }

        $pit38 = $result['pit38'];

        if (! $pit38->hasCompletePersonalData()) {
            $this->addFlash('warning', 'Uzupelnij swoj NIP i dane osobowe w profilu, aby wygenerowac PIT/ZG.');

            return $this->redirectToRoute('profile_edit');
        }

        $country = CountryCode::fromString($countryCode);
        $dividendData = $result['summary']->dividendsByCountry[$country->value] ?? null;

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
     * @return array{pit38: PIT38Data, summary: TaxSummaryResult}|null
     */
    private function buildPIT38WithSummary(int $taxYear): ?array
    {
        $userId = $this->resolveUserId();

        if ($this->importedTxRepo->countByUser($userId) === 0) {
            return null;
        }

        $summary = ($this->taxSummaryHandler)(new GetTaxSummary($userId, TaxYear::of($taxYear)));

        $user = $this->userRepository->findById($userId);
        $nip = $user?->nip();
        $firstName = $user?->firstName();
        $lastName = $user?->lastName();

        return [
            'pit38' => $this->summaryToPIT38($summary, $nip, $firstName, $lastName),
            'summary' => $summary,
        ];
    }

    private function buildPIT38Data(int $taxYear): ?PIT38Data
    {
        $result = $this->buildPIT38WithSummary($taxYear);

        return $result !== null ? $result['pit38'] : null;
    }

    private function summaryToPIT38(
        TaxSummaryResult $summary,
        ?string $nip,
        ?string $firstName,
        ?string $lastName,
    ): PIT38Data {
        $equityGainFloat = (float) $summary->equityGainLoss;
        $equityIncome = $equityGainFloat > 0 ? $summary->equityGainLoss : '0.00';
        $equityLoss = $equityGainFloat < 0 ? ltrim($summary->equityGainLoss, '-') : '0.00';

        $cryptoGainFloat = (float) $summary->cryptoGainLoss;
        $cryptoIncome = $cryptoGainFloat > 0 ? $summary->cryptoGainLoss : '0.00';
        $cryptoLoss = $cryptoGainFloat < 0 ? ltrim($summary->cryptoGainLoss, '-') : '0.00';

        $dividendGross = '0.00';
        $dividendWHT = '0.00';
        foreach ($summary->dividendsByCountry as $country) {
            $dividendGross = bcadd($dividendGross, $country->grossDividendPLN, 2);
            $dividendWHT = bcadd($dividendWHT, $country->whtPaidPLN, 2);
        }

        // equityCosts = costBasis + commissions (PIT-38 field)
        $equityCosts = bcadd($summary->equityCostBasis, $summary->equityCommissions, 2);
        $cryptoCosts = bcadd($summary->cryptoCostBasis, $summary->cryptoCommissions, 2);

        return new PIT38Data(
            taxYear: $summary->taxYear,
            nip: $nip,
            firstName: $firstName,
            lastName: $lastName,
            equityProceeds: $summary->equityProceeds,
            equityCosts: $equityCosts,
            equityIncome: $equityIncome,
            equityLoss: $equityLoss,
            equityTaxBase: $summary->equityTaxableIncome,
            equityTax: $summary->equityTax,
            dividendGross: $dividendGross,
            dividendWHT: $dividendWHT,
            dividendTaxDue: $summary->dividendTotalTaxDue,
            cryptoProceeds: $summary->cryptoProceeds,
            cryptoCosts: $cryptoCosts,
            cryptoIncome: $cryptoIncome,
            cryptoLoss: $cryptoLoss,
            cryptoTax: $summary->cryptoTax,
            totalTax: $summary->totalTaxDue,
            isCorrection: false,
        );
    }

    private function checkValueGate(int $taxYear): ?Response
    {
        $userId = $this->resolveUserId();

        $brokerCount = $this->importedTxRepo->countBrokersByUser($userId);
        $closedPositionCount = $this->importedTxRepo->countSellsByUserAndYear($userId, $taxYear);

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

    private function resolveUserId(): UserId
    {
        /** @var SecurityUser $user */
        $user = $this->getUser();

        return UserId::fromString($user->id());
    }
}
