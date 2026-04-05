<?php

declare(strict_types=1);

namespace App\Declaration\Infrastructure\Controller;

use App\Declaration\Application\DeclarationService;
use App\Declaration\Application\Result\NoData;
use App\Declaration\Application\Result\PIT38WithSummary;
use App\Declaration\Domain\DTO\PITZGData;
use App\Declaration\Domain\Service\PITZGGenerator;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\UserId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DeclarationPitZgController extends AbstractController
{
    public function __construct(
        private readonly DeclarationService $declarationService,
        private readonly PITZGGenerator $pitzgGenerator,
    ) {
    }

    #[Route('/declaration/{taxYear}/pitzg/{countryCode}', name: 'declaration_pitzg', methods: ['GET'], requirements: [
        'taxYear' => '\d{4}',
        'countryCode' => '[A-Z]{2}',
    ])]
    public function __invoke(int $taxYear, string $countryCode): Response
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
}
