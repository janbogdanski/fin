<?php

declare(strict_types=1);

namespace App\TaxCalc\Infrastructure\Controller;

use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Port\PriorYearLossCrudPort;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class LossesDeleteController extends AbstractController
{
    public function __construct(
        private readonly PriorYearLossCrudPort $repository,
    ) {
    }

    #[Route('/losses/{id}/delete', name: 'losses_delete', methods: ['POST'], requirements: [
        'id' => '[0-9a-f\-]{36}',
    ])]
    public function __invoke(Request $request, string $id): Response
    {
        if (! Uuid::isValid($id)) {
            $this->addFlash('error', 'Nieprawidlowy identyfikator.');

            return $this->redirectToRoute('losses_index');
        }

        $token = $request->request->getString('_token');

        if (! $this->isCsrfTokenValid('losses_delete_' . $id, $token)) {
            $this->addFlash('error', 'Nieprawidlowy token CSRF.');

            return $this->redirectToRoute('losses_index');
        }

        /** @var SecurityUser|null $user */
        $user = $this->getUser();

        if ($user === null) {
            throw new \RuntimeException('User must be authenticated to manage losses.');
        }

        $userId = UserId::fromString($user->id());

        try {
            $this->repository->delete($id, $userId);
        } catch (\DomainException) {
            $this->addFlash(
                'error',
                'Nie mozna usunac straty, ktora zostala juz uzywa w rozliczeniu podatkowym.',
            );

            return $this->redirectToRoute('losses_index');
        }

        $this->addFlash('success', 'Strata zostala usunieta.');

        return $this->redirectToRoute('losses_index');
    }
}
