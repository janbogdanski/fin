<?php

declare(strict_types=1);

namespace App\Declaration\Infrastructure\Controller;

use App\Declaration\Domain\DTO\PIT38Data;
use App\Declaration\Domain\Service\PIT38XMLGenerator;
use App\TaxCalc\Application\Query\TaxSummaryDividendCountry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/declaration')]
final class DeclarationController extends AbstractController
{
    public function __construct(
        private readonly PIT38XMLGenerator $xmlGenerator,
    ) {
    }

    #[Route('/{taxYear}/preview', name: 'declaration_preview', methods: ['GET'], requirements: [
        'taxYear' => '\d{4}',
    ])]
    public function preview(int $taxYear): Response
    {
        $pit38 = $this->getMockPIT38Data($taxYear);

        return $this->render('declaration/preview.html.twig', [
            'pit38' => $pit38,
        ]);
    }

    #[Route('/{taxYear}/export/xml', name: 'declaration_export_xml', methods: ['GET'], requirements: [
        'taxYear' => '\d{4}',
    ])]
    public function exportXml(int $taxYear): Response
    {
        $pit38 = $this->getMockPIT38Data($taxYear);
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
        // Mock — will be replaced by actual PDF generation service
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
        $dividendData = $this->getMockDividendData();

        if (! isset($dividendData[$countryCode])) {
            throw $this->createNotFoundException(sprintf('Brak danych PIT/ZG dla kraju: %s', $countryCode));
        }

        return $this->render('declaration/pitzg.html.twig', [
            'taxYear' => $taxYear,
            'country' => $dividendData[$countryCode],
        ]);
    }

    /**
     * Mock data — will be replaced by Query bus once persistence is ready.
     */
    private function getMockPIT38Data(int $taxYear): PIT38Data
    {
        return new PIT38Data(
            taxYear: $taxYear,
            nip: '5260000005',
            firstName: 'Jan',
            lastName: 'Kowalski',
            equityProceeds: '125430.50',
            equityCosts: '99430.45',
            equityIncome: '26000.05',
            equityLoss: '0.00',
            equityTaxBase: '26000.05',
            equityTax: '4940.01',
            dividendGross: '13150.00',
            dividendWHT: '2397.00',
            dividendTaxDue: '340.00',
            cryptoProceeds: '45000.00',
            cryptoCosts: '38450.00',
            cryptoIncome: '6550.00',
            cryptoLoss: '0.00',
            cryptoTax: '1244.50',
            totalTax: '6524.51',
            isCorrection: false,
        );
    }

    /**
     * @return array<string, TaxSummaryDividendCountry>
     */
    private function getMockDividendData(): array
    {
        return [
            'US' => new TaxSummaryDividendCountry(
                countryCode: 'US',
                grossDividendPLN: '8500.00',
                whtPaidPLN: '1275.00',
                polishTaxDue: '340.00',
            ),
            'DE' => new TaxSummaryDividendCountry(
                countryCode: 'DE',
                grossDividendPLN: '3200.00',
                whtPaidPLN: '832.00',
                polishTaxDue: '0.00',
            ),
            'IE' => new TaxSummaryDividendCountry(
                countryCode: 'IE',
                grossDividendPLN: '1450.00',
                whtPaidPLN: '290.00',
                polishTaxDue: '0.00',
            ),
        ];
    }
}
