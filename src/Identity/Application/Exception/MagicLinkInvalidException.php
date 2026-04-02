<?php

declare(strict_types=1);

namespace App\Identity\Application\Exception;

final class MagicLinkInvalidException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Magic link is invalid or has already been used.');
    }
}
