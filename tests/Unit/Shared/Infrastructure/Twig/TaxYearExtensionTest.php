<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Twig;

use App\Shared\Domain\Service\DefaultTaxYearResolver;
use App\Shared\Infrastructure\Twig\TaxYearExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class TaxYearExtensionTest extends TestCase
{
    public function testGetDefaultTaxYearDelegatesToResolver(): void
    {
        $clock = new MockClock(new \DateTimeImmutable('2026-04-02'));
        $resolver = new DefaultTaxYearResolver();
        $extension = new TaxYearExtension($resolver, $clock);

        self::assertSame(2025, $extension->getDefaultTaxYear());
    }

    public function testExposesDefaultTaxYearFunction(): void
    {
        $clock = new MockClock(new \DateTimeImmutable('2026-06-15'));
        $resolver = new DefaultTaxYearResolver();
        $extension = new TaxYearExtension($resolver, $clock);

        $functions = $extension->getFunctions();

        self::assertCount(1, $functions);
        self::assertSame('default_tax_year', $functions[0]->getName());
    }
}
