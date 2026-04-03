<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Controller;

use App\Identity\Application\Command\ApplyReferralCode;
use App\Identity\Application\Command\ApplyReferralCodeHandler;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\ValueObject\UserId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileController extends AbstractController
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly ApplyReferralCodeHandler $applyReferralCodeHandler,
    ) {
    }

    #[Route('/profile', name: 'profile_edit', methods: ['GET'])]
    public function edit(): Response
    {
        $user = $this->loadDomainUser();

        return $this->render('profile/edit.html.twig', [
            'nip' => $user->nip() ?? '',
            'firstName' => $user->firstName() ?? '',
            'lastName' => $user->lastName() ?? '',
            'referralCode' => $user->referralCode(),
            'referredBy' => $user->referredBy(),
            'bonusTransactions' => $user->bonusTransactions(),
        ]);
    }

    #[Route('/profile', name: 'profile_update', methods: ['POST'])]
    public function update(Request $request): Response
    {
        if (! $this->isCsrfTokenValid('profile_update', $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'Nieprawidlowy token CSRF. Sprobuj ponownie.');

            return $this->redirectToRoute('profile_edit');
        }

        $nip = trim($request->request->getString('nip'));
        $firstName = trim($request->request->getString('first_name'));
        $lastName = trim($request->request->getString('last_name'));

        $user = $this->loadDomainUser();

        try {
            $user->updateProfile($nip, $firstName, $lastName);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->render('profile/edit.html.twig', [
                'nip' => $nip,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'referralCode' => $user->referralCode(),
                'referredBy' => $user->referredBy(),
                'bonusTransactions' => $user->bonusTransactions(),
            ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        $this->userRepository->flush();

        $this->addFlash('success', 'Profil zostal zapisany.');

        return $this->redirectToRoute('profile_edit');
    }

    #[Route('/profile/referral', name: 'profile_apply_referral', methods: ['POST'])]
    public function applyReferral(Request $request): Response
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

    private function loadDomainUser(): \App\Identity\Domain\Model\User
    {
        /** @var SecurityUser $securityUser */
        $securityUser = $this->getUser();

        $user = $this->userRepository->findById(UserId::fromString($securityUser->id()));

        if ($user === null) {
            throw $this->createNotFoundException('User not found');
        }

        return $user;
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
