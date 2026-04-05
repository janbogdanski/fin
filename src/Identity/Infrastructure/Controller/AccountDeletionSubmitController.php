<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Controller;

use App\Identity\Application\Command\AnonymizeUser;
use App\Identity\Application\Command\AnonymizeUserHandler;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\ValueObject\UserId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Processes the account deletion request (POST).
 *
 * Validates CSRF, anonymizes the user, invalidates the session, redirects to /.
 * Requires ROLE_USER — covered by global access_control in security.yaml.
 */
final class AccountDeletionSubmitController extends AbstractController
{
    public function __construct(
        private readonly AnonymizeUserHandler $anonymizeUserHandler,
    ) {
    }

    #[Route('/account/delete', name: 'account_delete_submit', methods: ['POST'])]
    public function __invoke(Request $request, #[CurrentUser] SecurityUser $securityUser): Response
    {
        if (! $this->isCsrfTokenValid('account_delete', $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'Nieprawidlowy token CSRF. Sprobuj ponownie.');

            return $this->redirectToRoute('account_delete_confirm');
        }

        ($this->anonymizeUserHandler)(new AnonymizeUser(
            userId: UserId::fromString($securityUser->id()),
        ));

        $this->addFlash('success', 'Twoje konto zostalo usuniete.');

        // Invalidate the session — user is no longer authenticated after erasure
        // Flash must be added before invalidate() — the session is destroyed after this call
        $request->getSession()->invalidate();

        return new RedirectResponse($this->generateUrl('landing_index'));
    }
}
