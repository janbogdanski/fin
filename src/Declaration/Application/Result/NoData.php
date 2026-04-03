<?php

declare(strict_types=1);

namespace App\Declaration\Application\Result;

/**
 * User has no imported transactions — cannot generate declaration.
 */
final readonly class NoData implements DeclarationResult
{
}
