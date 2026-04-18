<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Controller;

use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\ValueObject\UserId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileEditController extends AbstractController
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    #[Route('/profile', name: 'profile_edit', methods: ['GET'])]
    public function __invoke(): Response
    {
        /** @var SecurityUser $securityUser */
        $securityUser = $this->getUser();
        $user = $this->userRepository->findById(UserId::fromString($securityUser->id()));

        if ($user === null) {
            throw $this->createNotFoundException('User not found');
        }

        return $this->render('profile/edit.html.twig', [
            'nip' => $user->nip() ?? '',
            'pesel' => $user->pesel() ?? '',
            'firstName' => $user->firstName() ?? '',
            'lastName' => $user->lastName() ?? '',
            'referralCode' => $user->referralCode(),
            'referredBy' => $user->referredBy(),
            'bonusTransactions' => $user->bonusTransactions(),
        ]);
    }
}
