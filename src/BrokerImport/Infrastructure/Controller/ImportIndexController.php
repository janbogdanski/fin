<?php

declare(strict_types=1);

namespace App\BrokerImport\Infrastructure\Controller;

use App\BrokerImport\Infrastructure\Adapter\AdapterRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ImportIndexController extends AbstractController
{
    public function __construct(
        private readonly AdapterRegistry $adapterRegistry,
    ) {
    }

    #[Route('/import', name: 'import_index', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('import/index.html.twig', [
            'supportedBrokers' => $this->adapterRegistry->supportedBrokers(),
            'adapterChoices' => $this->adapterRegistry->adapterChoices(),
        ]);
    }
}
