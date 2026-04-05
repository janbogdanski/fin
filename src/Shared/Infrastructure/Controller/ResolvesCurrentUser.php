<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Controller;

use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\ValueObject\UserId;

trait ResolvesCurrentUser
{
    private function resolveUserId(): UserId
    {
        /** @var SecurityUser $user */
        $user = $this->getUser();

        return UserId::fromString($user->id());
    }
}
