<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain;

use App\Shared\Domain\Service\DefaultTaxYearResolver;
use PHPUnit\Framework\TestCase;

/**
 * Mutation-killing tests for DefaultTaxYearResolver.
 *
 * Targets: CastInt on (int) $now->format('Y') and (int) $now->format('n'),
 * boundary at month 5.
 */
final class DefaultTaxYearResolverMutationTest extends TestCase
{
    /**
     * Kills CastInt on format('Y'): without cast, year could be string comparison.
     * The returned value must be strictly integer.
     */
    public function testResolveReturnsIntegerType(): void
    {
        $resolver = new DefaultTaxYearResolver();

        $result = $resolver->resolve(new \DateTimeImmutable('2025-06-15'));

        self::assertIsInt($result);
        self::assertSame(2025, $result);
    }

    /**
     * Kills boundary mutant: < changed to <=.
     * Month 5 (May) should return CURRENT year, not previous.
     * With <= mutation, month 5 would return previous year.
     */
    public function testMayReturnsCurrent(): void
    {
        $resolver = new DefaultTaxYearResolver();

        self::assertSame(2025, $resolver->resolve(new \DateTimeImmutable('2025-05-15')));
    }

    /**
     * Kills boundary mutant: < changed to <=.
     * Month 4 (April) should return PREVIOUS year.
     */
    public function testAprilReturnsPrevious(): void
    {
        $resolver = new DefaultTaxYearResolver();

        self::assertSame(2024, $resolver->resolve(new \DateTimeImmutable('2025-04-15')));
    }
}
