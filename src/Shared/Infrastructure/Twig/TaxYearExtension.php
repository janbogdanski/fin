<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Twig;

use App\TaxCalc\Domain\Service\DefaultTaxYearResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes default_tax_year() in Twig templates.
 * Uses DefaultTaxYearResolver to determine the year dynamically.
 */
final class TaxYearExtension extends AbstractExtension
{
    public function __construct(
        private readonly DefaultTaxYearResolver $resolver,
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('default_tax_year', $this->getDefaultTaxYear(...)),
        ];
    }

    public function getDefaultTaxYear(): int
    {
        return $this->resolver->resolve();
    }
}
