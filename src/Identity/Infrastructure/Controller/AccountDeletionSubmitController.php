<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Controller;

use App\Identity\Application\Command\AnonymizeUser;
use App\Identity\Application\Command\AnonymizeUserHandler;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\Port\AuditLogPort;
use App\Shared\Domain\ValueObject\UserId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
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
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RateLimiterFactory $accountDeleteLimiter,
        private readonly AuditLogPort $auditLogger,
    ) {
    }

    #[Route('/account/delete', name: 'account_delete_submit', methods: ['POST'])]
    public function __invoke(Request $request, #[CurrentUser] SecurityUser $securityUser): Response
    {
        $limiter = $this->accountDeleteLimiter->create($securityUser->id());
        if (! $limiter->consume(1)->isAccepted()) {
            $this->addFlash('error', 'Zbyt wiele prob. Sprobuj ponownie za chwile.');

            return $this->redirectToRoute('account_delete_confirm');
        }

        if (! $this->isCsrfTokenValid('account_delete', $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'Nieprawidlowy token CSRF. Sprobuj ponownie.');

            return $this->redirectToRoute('account_delete_confirm');
        }

        ($this->anonymizeUserHandler)(new AnonymizeUser(
            userId: UserId::fromString($securityUser->id()),
        ));

        $this->addFlash('success', 'Twoje konto zostalo usuniete.');

        $this->auditLogger->log('user.anonymized', $securityUser->id(), [], $request->getClientIp());

        // Flash must be written before session is destroyed
        // Clear security token first, then invalidate session (defence-in-depth)
        $this->tokenStorage->setToken(null);
        $request->getSession()->invalidate();

        return new RedirectResponse($this->generateUrl('landing_index'));
    }
}
