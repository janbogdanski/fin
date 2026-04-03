<?php

declare(strict_types=1);

namespace App\Tests\Unit\BrokerImport;

use App\BrokerImport\Domain\Exception\BrokerFileMismatchException;
use PHPUnit\Framework\TestCase;

final class BrokerFileMismatchExceptionTest extends TestCase
{
    public function testExceptionMessageContainsBrokerAndFilename(): void
    {
        $exception = new BrokerFileMismatchException('ibkr', 'degiro_export.csv');

        self::assertStringContainsString('ibkr', $exception->getMessage());
        self::assertStringContainsString('degiro_export.csv', $exception->getMessage());
        self::assertSame('ibkr', $exception->selectedBrokerId);
    }
}
