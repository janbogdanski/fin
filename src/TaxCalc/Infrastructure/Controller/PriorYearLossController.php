<?php

declare(strict_types=1);

namespace App\TaxCalc\Infrastructure\Controller;

use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Port\PriorYearLossCrudPort;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Controller for managing prior year losses (straty z lat ubieglych).
 *
 * AC2: User can enter prior year losses via form (GET/POST /losses).
 * AC5: Loss older than 5 years rejected.
 *
 * @see art. 9 ust. 3 ustawy o PIT
 */
final class PriorYearLossController extends AbstractController
{
    /**
     * Max 5 years for carry-forward (art. 9 ust. 3 ustawy o PIT).
     */
    private const int CARRY_FORWARD_YEARS = 5;

    /**
     * Upper limit for loss amount in PLN (100 million).
     */
    private const string MAX_LOSS_AMOUNT = '100000000';

    public function __construct(
        private readonly PriorYearLossCrudPort $repository,
        private readonly RateLimiterFactory $lossesStoreLimiter,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/losses', name: 'losses_index', methods: ['GET'])]
    public function index(): Response
    {
        $userId = $this->resolveUserId();
        $losses = $this->repository->findByUser($userId);
        $currentYear = (int) $this->clock->now()->format('Y');

        return $this->render('losses/index.html.twig', [
            'losses' => $losses,
            'currentYear' => $currentYear,
            'minLossYear' => $currentYear - self::CARRY_FORWARD_YEARS,
            'maxLossYear' => $currentYear - 1,
            'taxCategories' => TaxCategory::cases(),
        ]);
    }

    #[Route('/losses', name: 'losses_store', methods: ['POST'])]
    public function store(Request $request): Response
    {
        $limiter = $this->lossesStoreLimiter->create($request->getClientIp() ?? 'unknown');

        if (! $limiter->consume()->isAccepted()) {
            $this->addFlash('error', 'Zbyt wiele prob. Sprobuj ponownie za kilka minut.');

            return $this->redirectToRoute('losses_index');
        }

        $token = $request->request->getString('_token');

        if (! $this->isCsrfTokenValid('losses_store', $token)) {
            $this->addFlash('error', 'Nieprawidlowy token CSRF. Sprobuj ponownie.');

            return $this->redirectToRoute('losses_index');
        }

        $lossYear = $request->request->getInt('loss_year');
        $taxCategory = $request->request->getString('tax_category');
        $amount = $request->request->getString('amount');
        $currentYear = (int) $this->clock->now()->format('Y');

        // AC5: Validate loss year
        if (self::isLossYearInvalid($lossYear, $currentYear)) {
            $this->addFlash('error', 'Rok straty musi byc wczesniejszy niz biezacy rok podatkowy.');

            return $this->redirectToRoute('losses_index');
        }

        if (self::isLossYearExpired($lossYear, $currentYear)) {
            $this->addFlash(
                'error',
                sprintf(
                    'Strata z roku %d wygasla. Mozna odliczac straty z ostatnich %d lat (%d-%d).',
                    $lossYear,
                    self::CARRY_FORWARD_YEARS,
                    $currentYear - self::CARRY_FORWARD_YEARS,
                    $currentYear - 1,
                ),
            );

            return $this->redirectToRoute('losses_index');
        }

        // Validate category
        $category = TaxCategory::tryFrom($taxCategory);

        if ($category === null) {
            $this->addFlash('error', 'Nieprawidlowa kategoria podatkowa.');

            return $this->redirectToRoute('losses_index');
        }

        // Validate amount using BigDecimal (no float precision loss)
        $amount = str_replace(',', '.', $amount);
        $amount = trim($amount);

        try {
            $bigAmount = BigDecimal::of($amount);
        } catch (MathException) {
            $this->addFlash('error', 'Kwota straty musi byc liczba wieksza od zera.');

            return $this->redirectToRoute('losses_index');
        }

        if ($bigAmount->isNegativeOrZero()) {
            $this->addFlash('error', 'Kwota straty musi byc liczba wieksza od zera.');

            return $this->redirectToRoute('losses_index');
        }

        if ($bigAmount->isGreaterThan(BigDecimal::of(self::MAX_LOSS_AMOUNT))) {
            $this->addFlash('error', sprintf('Kwota straty nie moze przekraczac %s PLN.', number_format((float) self::MAX_LOSS_AMOUNT, 0, '', ' ')));

            return $this->redirectToRoute('losses_index');
        }

        // Format to 2 decimal places
        $amount = $bigAmount->toScale(2)->__toString();

        $userId = $this->resolveUserId();
        $this->repository->save($userId, $lossYear, $category->value, $amount);

        $this->addFlash(
            'success',
            sprintf('Dodano strate z roku %d: %s PLN (%s).', $lossYear, $amount, $this->categoryLabel($category)),
        );

        return $this->redirectToRoute('losses_index');
    }

    #[Route('/losses/{id}/delete', name: 'losses_delete', methods: ['POST'], requirements: [
        'id' => '[0-9a-f\-]{36}',
    ])]
    public function delete(Request $request, string $id): Response
    {
        if (! Uuid::isValid($id)) {
            $this->addFlash('error', 'Nieprawidlowy identyfikator.');

            return $this->redirectToRoute('losses_index');
        }

        $token = $request->request->getString('_token');

        if (! $this->isCsrfTokenValid('losses_delete_' . $id, $token)) {
            $this->addFlash('error', 'Nieprawidlowy token CSRF.');

            return $this->redirectToRoute('losses_index');
        }

        $userId = $this->resolveUserId();
        $this->repository->delete($id, $userId);

        $this->addFlash('success', 'Strata zostala usunieta.');

        return $this->redirectToRoute('losses_index');
    }

    /**
     * AC5: Check if loss year has expired (older than 5 years).
     * Exported as public static for unit testing.
     */
    public static function isLossYearExpired(int $lossYear, int $currentYear): bool
    {
        return $lossYear < $currentYear - self::CARRY_FORWARD_YEARS;
    }

    /**
     * Loss year must be strictly before the current year (prior-year loss).
     */
    public static function isLossYearInvalid(int $lossYear, int $currentYear): bool
    {
        return $lossYear >= $currentYear;
    }

    private function resolveUserId(): UserId
    {
        /** @var SecurityUser|null $user */
        $user = $this->getUser();

        if ($user === null) {
            throw new \RuntimeException('User must be authenticated to manage losses.');
        }

        return UserId::fromString($user->id());
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
