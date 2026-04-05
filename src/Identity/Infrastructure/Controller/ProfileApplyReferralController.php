<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Controller;

use App\Identity\Application\Command\ApplyReferralCode;
use App\Identity\Application\Command\ApplyReferralCodeHandler;
use App\Identity\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileApplyReferralController extends AbstractController
{
    public function __construct(
        private readonly ApplyReferralCodeHandler $applyReferralCodeHandler,
    ) {
    }

    #[Route('/profile/referral', name: 'profile_apply_referral', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        if (! $this->isCsrfTokenValid('apply_referral', $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'Nieprawidlowy token CSRF. Sprobuj ponownie.');

            return $this->redirectToRoute('profile_edit');
        }

        $referralCode = strtoupper(trim($request->request->getString('referral_code')));

        if ($referralCode === '') {
            $this->addFlash('error', 'Podaj kod polecajacego.');

            return $this->redirectToRoute('profile_edit');
        }

        /** @var SecurityUser $securityUser */
        $securityUser = $this->getUser();

        try {
            ($this->applyReferralCodeHandler)(new ApplyReferralCode(
                refereeUserId: $securityUser->id(),
                referralCode: $referralCode,
            ));
        } catch (\DomainException $e) {
            $this->addFlash('error', $this->translateReferralError($e->getMessage()));

            return $this->redirectToRoute('profile_edit');
        }

        $this->addFlash('success', 'Kod polecajacego zostal zastosowany! Otrzymales dodatkowe darmowe transakcje.');

        return $this->redirectToRoute('profile_edit');
    }

    private function translateReferralError(string $message): string
    {
        return match ($message) {
            'Cannot refer yourself' => 'Nie mozesz uzyc wlasnego kodu polecajacego.',
            'Referral code already applied' => 'Kod polecajacego zostal juz wykorzystany.',
            'Invalid referral code' => 'Nieprawidlowy kod polecajacego.',
            'User not found' => 'Nie znaleziono uzytkownika.',
            default => $message,
        };
    }
}
