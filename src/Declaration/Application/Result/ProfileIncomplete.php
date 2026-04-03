<?php

declare(strict_types=1);

namespace App\Declaration\Application\Result;

/**
 * User profile is missing required personal data (NIP, name) for XML generation.
 */
final readonly class ProfileIncomplete implements DeclarationResult
{
}
