<?php

declare(strict_types=1);

namespace App\Tests\InMemory;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Port\ClosedPositionQueryPort;
use App\TaxCalc\Domain\Model\ClosedPosition;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;

/**
 * In-memory implementation of ClosedPositionQueryPort for testing.
 */
final class InMemoryClosedPositionQueryAdapter implements ClosedPositionQueryPort
{
    /**
     * @var list<array{userId: string, category: string, position: ClosedPosition}>
     */
    private array $store = [];

    public function seed(UserId $userId, ClosedPosition $position, TaxCategory $category): void
    {
        $this->store[] = [
            'userId' => $userId->toString(),
            'category' => $category->value,
            'position' => $position,
        ];
    }

    /**
     * @return list<ClosedPosition>
     */
    public function findByUserYearAndCategory(UserId $userId, TaxYear $taxYear, TaxCategory $category): array
    {
        $filtered = array_filter(
            $this->store,
            static fn (array $e): bool => $e['userId'] === $userId->toString()
                && $e['category'] === $category->value
                && (int) $e['position']->sellDate->format('Y') === $taxYear->value,
        );

        return array_values(array_map(static fn (array $e): ClosedPosition => $e['position'], $filtered));
    }

    public function countByUserAndYear(UserId $userId, TaxYear $taxYear): int
    {
        return count(array_filter(
            $this->store,
            static fn (array $e): bool => $e['userId'] === $userId->toString()
                && (int) $e['position']->sellDate->format('Y') === $taxYear->value,
        ));
    }
}
