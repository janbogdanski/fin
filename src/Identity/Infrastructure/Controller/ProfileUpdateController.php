<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Controller;

use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\ValueObject\UserId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileUpdateController extends AbstractController
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    #[Route('/profile', name: 'profile_update', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        if (! $this->isCsrfTokenValid('profile_update', $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'Nieprawidlowy token CSRF. Sprobuj ponownie.');

            return $this->redirectToRoute('profile_edit');
        }

        $nip = trim($request->request->getString('nip'));
        $pesel = trim($request->request->getString('pesel'));
        $firstName = trim($request->request->getString('first_name'));
        $lastName = trim($request->request->getString('last_name'));

        $nipNormalized = $nip !== '' ? $nip : null;
        $peselNormalized = $pesel !== '' ? $pesel : null;

        /** @var SecurityUser $securityUser */
        $securityUser = $this->getUser();
        $user = $this->userRepository->findById(UserId::fromString($securityUser->id()));

        if ($user === null) {
            throw $this->createNotFoundException('User not found');
        }

        try {
            $user->updateProfile($nipNormalized, $peselNormalized, $firstName, $lastName);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->render('profile/edit.html.twig', [
                'nip' => $nip,
                'pesel' => $pesel,
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
}
