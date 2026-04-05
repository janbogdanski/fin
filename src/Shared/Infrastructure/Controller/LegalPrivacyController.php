<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/polityka-prywatnosci', name: 'legal_privacy', methods: ['GET'])]
final class LegalPrivacyController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('legal/privacy.html.twig');
    }
}
