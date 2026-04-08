<?php

declare(strict_types=1);

namespace App\BrokerImport\Application\Port;

use App\BrokerImport\Application\DTO\ParseResult;
use App\Shared\Domain\ValueObject\BrokerId;

interface BrokerAdapterInterface
{
    public function brokerId(): BrokerId;

    public function supports(string $content, string $filename): bool;

    public function parse(string $fileContent, string $filename = ''): ParseResult;

    /**
     * Detection priority. Higher = checked first.
     * Most specific adapters (IBKR, Bossa) should return higher values
     * than generic ones (Degiro, Revolut).
     */
    public function priority(): int;
}
