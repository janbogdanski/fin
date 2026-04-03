<?php

declare(strict_types=1);

namespace App\Identity\Application\Command;

use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Shared\Domain\ValueObject\UserId;

final readonly class ApplyReferralCodeHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function __invoke(ApplyReferralCode $command): void
    {
        $referee = $this->userRepository->findById(UserId::fromString($command->refereeUserId));

        if ($referee === null) {
            throw new \DomainException('User not found');
        }

        $referrer = $this->userRepository->findByReferralCode($command->referralCode);

        if ($referrer === null) {
            throw new \DomainException('Invalid referral code');
        }

        $referee->applyReferral($referrer);

        $this->userRepository->flush();
    }
}
