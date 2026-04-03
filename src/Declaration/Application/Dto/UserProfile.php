<?php

declare(strict_types=1);

namespace App\Declaration\Application\Dto;

/**
 * User profile data needed for PIT-38 declaration generation.
 */
final readonly class UserProfile
{
    public function __construct(
        public string $firstName,
        public string $lastName,
    ) {
    }
}
