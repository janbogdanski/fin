<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

final readonly class LoginController
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    #[Route('/login', name: 'auth_login', methods: ['GET'])]
    public function __invoke(): Response
    {
        return new Response($this->twig->render('auth/login.html.twig'));
    }
}
