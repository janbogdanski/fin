<?php

declare(strict_types=1);

namespace App\Identity\Application\Port;

use App\Identity\Domain\Model\MagicLinkToken;
use App\Identity\Domain\Model\User;

interface MagicLinkTokenGeneratorPort
{
    public function generate(User $user): MagicLinkToken;
}
