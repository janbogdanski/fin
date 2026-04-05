<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Renders the account deletion confirmation page (GET).
 *
 * Requires ROLE_USER — covered by global access_control in security.yaml.
 */
final class AccountDeletionConfirmController extends AbstractController
{
    #[Route('/account/delete', name: 'account_delete_confirm', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('account/delete_confirmation.html.twig');
    }
}
