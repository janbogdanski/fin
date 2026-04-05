<?php

declare(strict_types=1);

namespace App\Identity\Application\Command;

use App\Shared\Domain\ValueObject\UserId;

final readonly class AnonymizeUser
{
    public function __construct(
        public readonly UserId $userId,
    ) {
    }
}
