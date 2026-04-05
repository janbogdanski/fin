<?php

declare(strict_types=1);

namespace App\TaxCalc\Infrastructure\Controller;

use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Port\PriorYearLossCrudPort;
use App\TaxCalc\Domain\Service\PriorYearLossRules;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Displays the prior year losses form and list.
 *
 * AC2: User can enter prior year losses via form (GET /losses).
 */
final class LossesIndexController extends AbstractController
{
    public function __construct(
        private readonly PriorYearLossCrudPort $repository,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/losses', name: 'losses_index', methods: ['GET'])]
    public function __invoke(): Response
    {
        /** @var SecurityUser|null $user */
        $user = $this->getUser();

        if ($user === null) {
            throw new \RuntimeException('User must be authenticated to manage losses.');
        }

        $userId = UserId::fromString($user->id());
        $losses = $this->repository->findByUser($userId);
        $currentYear = (int) $this->clock->now()->format('Y');

        return $this->render('losses/index.html.twig', [
            'losses' => $losses,
            'currentYear' => $currentYear,
            'minLossYear' => $currentYear - PriorYearLossRules::CARRY_FORWARD_YEARS,
            'maxLossYear' => $currentYear - 1,
            'taxCategories' => TaxCategory::cases(),
        ]);
    }
}
