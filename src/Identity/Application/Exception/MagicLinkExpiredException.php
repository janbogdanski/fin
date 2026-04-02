<?php

declare(strict_types=1);

namespace App\Identity\Application\Exception;

final class MagicLinkExpiredException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Magic link has expired.');
    }
}
