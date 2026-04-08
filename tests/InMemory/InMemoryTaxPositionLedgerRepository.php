<?php

declare(strict_types=1);

namespace App\Tests\InMemory;

use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Domain\Model\ClosedPosition;
use App\TaxCalc\Domain\Model\OpenPosition;
use App\TaxCalc\Domain\Model\TaxPositionLedger;
use App\TaxCalc\Domain\Repository\TaxPositionLedgerRepositoryInterface;
use App\TaxCalc\Domain\ValueObject\TaxYear;

final class InMemoryTaxPositionLedgerRepository implements TaxPositionLedgerRepositoryInterface
{
    /**
     * @var array<string, TaxPositionLedger>
     */
    private array $ledgers = [];

    /**
     * @var list<array{userId: string, closedPosition: ClosedPosition}>
     */
    private array $closedPositions = [];

    public function save(TaxPositionLedger $ledger): void
    {
        $key = $this->ledgerKey($ledger->userId(), $ledger->isin());

        $this->ledgers[$key] = TaxPositionLedger::reconstitute(
            $ledger->userId(),
            $ledger->isin(),
            $ledger->taxCategory(),
            array_map($this->cloneOpenPosition(...), $ledger->openPositions()),
        );

        foreach ($ledger->flushNewClosedPositions() as $closedPosition) {
            $this->closedPositions[] = [
                'userId' => $ledger->userId()->toString(),
                'closedPosition' => $closedPosition,
            ];
        }
    }

    public function findByUserAndISIN(UserId $userId, ISIN $isin): ?TaxPositionLedger
    {
        $stored = $this->ledgers[$this->ledgerKey($userId, $isin)] ?? null;
        if ($stored === null) {
            return null;
        }

        return TaxPositionLedger::reconstitute(
            $stored->userId(),
            $stored->isin(),
            $stored->taxCategory(),
            array_map($this->cloneOpenPosition(...), $stored->openPositions()),
        );
    }

    public function deleteClosedPositionsForUserAndYear(UserId $userId, TaxYear $taxYear): void
    {
        $this->closedPositions = array_values(array_filter(
            $this->closedPositions,
            static fn (array $entry): bool => $entry['userId'] !== $userId->toString()
                || (int) $entry['closedPosition']->sellDate->format('Y') !== $taxYear->value,
        ));
    }

    /**
     * @return list<ClosedPosition>
     */
    public function closedPositionsForUserAndYear(UserId $userId, TaxYear $taxYear): array
    {
        return array_values(array_map(
            static fn (array $entry): ClosedPosition => $entry['closedPosition'],
            array_filter(
                $this->closedPositions,
                static fn (array $entry): bool => $entry['userId'] === $userId->toString()
                    && (int) $entry['closedPosition']->sellDate->format('Y') === $taxYear->value,
            ),
        ));
    }

    private function ledgerKey(UserId $userId, ISIN $isin): string
    {
        return sprintf('%s|%s', $userId->toString(), $isin->toString());
    }

    private function cloneOpenPosition(OpenPosition $position): OpenPosition
    {
        return new OpenPosition(
            transactionId: $position->transactionId,
            date: $position->date,
            originalQuantity: $position->originalQuantity,
            remainingQuantity: $position->remainingQuantity(),
            costPerUnitPLN: $position->costPerUnitPLN,
            commissionPerUnitPLN: $position->commissionPerUnitPLN,
            nbpRate: $position->nbpRate,
            broker: $position->broker,
        );
    }
}
