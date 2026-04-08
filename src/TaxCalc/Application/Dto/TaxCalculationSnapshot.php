<?php

declare(strict_types=1);

namespace App\TaxCalc\Application\Dto;

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
        $this->id = sprintf(
            '%08x-%04x-4%03x-%x%03x-%012x',
            random_int(0, 0xffffffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff),
            random_int(8, 11),
            random_int(0, 0x0fff),
            random_int(0, 0xffffffffffff),
        );
        $this->generatedAt = new \DateTimeImmutable();
    }
}
