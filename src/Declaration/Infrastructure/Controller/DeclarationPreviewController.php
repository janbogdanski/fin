<?php

declare(strict_types=1);

namespace App\Declaration\Infrastructure\Controller;

use App\Declaration\Application\DeclarationService;
use App\Declaration\Application\Result\NoData;
use App\Declaration\Application\Result\PIT38WithSummary;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\ValueObject\UserId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DeclarationPreviewController extends AbstractController
{
    public function __construct(
        private readonly DeclarationService $declarationService,
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

        return $this->render('declaration/preview.html.twig', [
            'pit38' => $result->pit38,
            'foreignDividends' => $foreignDividends,
        ]);
    }
}
