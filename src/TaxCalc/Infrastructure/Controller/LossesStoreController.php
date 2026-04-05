<?php

declare(strict_types=1);

namespace App\TaxCalc\Infrastructure\Controller;

use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Command\SavePriorYearLoss;
use App\TaxCalc\Application\Port\PriorYearLossCrudPort;
use App\TaxCalc\Domain\Service\LossFormValidator;
use App\TaxCalc\Domain\Service\PriorYearLossRules;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Stores a new prior year loss entry.
 *
 * AC2: User can enter prior year losses via form (POST /losses).
 * AC5: Loss older than 5 years rejected.
 *
 * @see art. 9 ust. 3 ustawy o PIT
 */
final class LossesStoreController extends AbstractController
{
    public function __construct(
        private readonly PriorYearLossCrudPort $repository,
        private readonly RateLimiterFactory $lossesStoreLimiter,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/losses', name: 'losses_store', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $limiter = $this->lossesStoreLimiter->create($request->getClientIp() ?? 'unknown');

        if (! $limiter->consume()->isAccepted()) {
            $this->addFlash('error', 'Zbyt wiele prob. Sprobuj ponownie za kilka minut.');

            return $this->redirectToRoute('losses_index');
        }

        if (! $this->isCsrfTokenValid('losses_store', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Nieprawidlowy token CSRF. Sprobuj ponownie.');

            return $this->redirectToRoute('losses_index');
        }

        $lossYear = $request->request->getInt('loss_year');
        $currentYear = (int) $this->clock->now()->format('Y');

        if (PriorYearLossRules::isLossYearInvalid($lossYear, $currentYear)) {
            $this->addFlash('error', 'Rok straty musi byc wczesniejszy niz biezacy rok podatkowy.');

            return $this->redirectToRoute('losses_index');
        }

        if (PriorYearLossRules::isLossYearExpired($lossYear, $currentYear)) {
            $this->addFlash(
                'error',
                sprintf(
                    'Strata z roku %d wygasla. Mozna odliczac straty z ostatnich %d lat (%d-%d).',
                    $lossYear,
                    PriorYearLossRules::CARRY_FORWARD_YEARS,
                    $currentYear - PriorYearLossRules::CARRY_FORWARD_YEARS,
                    $currentYear - 1,
                ),
            );

            return $this->redirectToRoute('losses_index');
        }

        $category = LossFormValidator::parseCategory($request->request->getString('tax_category'));

        if ($category === null) {
            $this->addFlash('error', 'Nieprawidlowa kategoria podatkowa.');

            return $this->redirectToRoute('losses_index');
        }

        $amountResult = LossFormValidator::parseAmount($request->request->getString('amount'));

        if (! $amountResult['ok']) {
            $this->addFlash('error', $amountResult['error']);

            return $this->redirectToRoute('losses_index');
        }

        /** @var SecurityUser|null $user */
        $user = $this->getUser();

        if ($user === null) {
            throw new \RuntimeException('User must be authenticated to manage losses.');
        }

        $userId = UserId::fromString($user->id());
        $this->repository->save(new SavePriorYearLoss($userId, $lossYear, $category, $amountResult['amount']));

        $this->addFlash(
            'success',
            sprintf('Dodano strate z roku %d: %s PLN (%s).', $lossYear, $amountResult['amount'], $this->categoryLabel($category)),
        );

        return $this->redirectToRoute('losses_index');
    }

    private function categoryLabel(TaxCategory $category): string
    {
        return match ($category) {
            TaxCategory::EQUITY => 'Akcje/ETF',
            TaxCategory::DERIVATIVE => 'Instrumenty pochodne',
            TaxCategory::CRYPTO => 'Kryptowaluty',
        };
    }
}
