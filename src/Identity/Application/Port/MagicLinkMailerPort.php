<?php

declare(strict_types=1);

namespace App\Identity\Application\Port;

use App\Identity\Domain\Model\MagicLinkToken;

interface MagicLinkMailerPort
{
    public function sendMagicLink(string $email, MagicLinkToken $token): void;
}
