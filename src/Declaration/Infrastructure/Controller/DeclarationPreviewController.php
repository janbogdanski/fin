<?php

declare(strict_types=1);

namespace App\Declaration\Infrastructure\Controller;

use App\Declaration\Application\DeclarationService;
use App\Declaration\Application\Result\NoData;
use App\Declaration\Application\Result\PIT38WithSummary;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Port\ClosedPositionQueryPort;
use App\TaxCalc\Domain\Model\ClosedPosition;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\RoundingMode;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DeclarationPreviewController extends AbstractController
{
    public function __construct(
        private readonly DeclarationService $declarationService,
        private readonly ClosedPositionQueryPort $closedPositionQuery,
    ) {
    }

    #[Route('/declaration/{taxYear}/preview', name: 'declaration_preview', methods: ['GET'], requirements: [
        'taxYear' => '\d{4}',
    ])]
    public function __invoke(int $taxYear): Response
    {
        /** @var SecurityUser $user */
        $user = $this->getUser();
        $userId = UserId::fromString($user->id());

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

        $closedPositions = $this->closedPositionQuery->findByUserYearAndCategory(
            $userId,
            TaxYear::of($taxYear),
            TaxCategory::EQUITY,
        );

        usort($closedPositions, static fn (ClosedPosition $a, ClosedPosition $b): int => $a->sellDate <=> $b->sellDate);

        return $this->render('declaration/preview.html.twig', [
            'pit38' => $result->pit38,
            'foreignDividends' => $foreignDividends,
            'positionRows' => array_map($this->toRow(...), $closedPositions),
        ]);
    }

    /**
     * Maps ClosedPosition domain object to a flat array of pre-formatted strings for the template.
     * Avoids BigDecimal/object math in Twig.
     *
     * @return array<string, mixed>
     */
    private function toRow(ClosedPosition $pos): array
    {
        $scale2 = RoundingMode::HALF_UP;
        $totalCost = $pos->costBasisPLN
            ->plus($pos->buyCommissionPLN)
            ->plus($pos->sellCommissionPLN)
            ->toScale(2, $scale2);

        $gainLoss = $pos->gainLossPLN->toScale(2, $scale2);
        $proceeds = $pos->proceedsPLN->toScale(2, $scale2);

        $buyPLN = $pos->buyNBPRate->currency()->equals(CurrencyCode::PLN);
        $sellPLN = $pos->sellNBPRate->currency()->equals(CurrencyCode::PLN);

        return [
            'isin' => $pos->isin->toString(),
            'buyDate' => $pos->buyDate->format('Y-m-d'),
            'sellDate' => $pos->sellDate->format('Y-m-d'),
            'quantity' => rtrim(rtrim((string) $pos->quantity->toScale(8, $scale2), '0'), '.'),
            'costPLN' => (string) $totalCost,
            'proceedsPLN' => (string) $proceeds,
            'gainLossPLN' => (string) $gainLoss,
            'gainLossPositive' => $gainLoss->isPositive(),
            'gainLossNegative' => $gainLoss->isNegative(),
            'buyRate' => $buyPLN ? null : [
                'currency' => $pos->buyNBPRate->currency()->value,
                'rate' => number_format((float) (string) $pos->buyNBPRate->rate(), 4, ',', "\u{00A0}"),
                'date' => $pos->buyNBPRate->effectiveDate()->format('Y-m-d'),
            ],
            'sellRate' => $sellPLN ? null : [
                'currency' => $pos->sellNBPRate->currency()->value,
                'rate' => number_format((float) (string) $pos->sellNBPRate->rate(), 4, ',', "\u{00A0}"),
                'date' => $pos->sellNBPRate->effectiveDate()->format('Y-m-d'),
            ],
        ];
    }
}
