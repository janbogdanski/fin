<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/regulamin', name: 'legal_terms', methods: ['GET'])]
final class LegalTermsController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('legal/terms.html.twig');
    }
}
