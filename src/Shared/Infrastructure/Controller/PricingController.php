<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PricingController extends AbstractController
{
    #[Route('/cennik', name: 'pricing_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('pricing/index.html.twig');
    }
}
