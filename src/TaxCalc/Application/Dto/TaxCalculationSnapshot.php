<?php

declare(strict_types=1);

namespace App\TaxCalc\Application\Dto;

use Symfony\Component\Uid\Uuid;

/**
 * Immutable DTO representing a point-in-time snapshot of a finalized tax calculation.
 *
 * Created at XML export time. The xml_sha256 hash allows verifying that the exported
 * XML matches the numbers stored here — enabling end-to-end audit traceability.
 */
final readonly class TaxCalculationSnapshot
{
    public string $id;
    public \DateTimeImmutable $generatedAt;

    public function __construct(
        public string $userId,
        public int $taxYear,
        public string $equityGainLoss,
        public string $equityTaxBase,
        public string $equityTaxDue,
        public string $priorLossesApplied,
        public string $dividendIncome,
        public string $dividendTaxDue,
        public string $xmlSha256,
    ) {
        $this->id = Uuid::v4()->toRfc4122();
        $this->generatedAt = new \DateTimeImmutable();
    }
}
