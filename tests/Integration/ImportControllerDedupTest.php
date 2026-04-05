<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\BrokerImport\Application\Port\ImportStoragePort;
use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\UserId;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * P1-015: Verifies duplicate CSV detection via content hash in DB.
 *
 * Tests the ImportStoragePort (Doctrine-backed) dedup behavior directly,
 * bypassing the controller layer (which requires authenticated user).
 * Controller-level integration tests require WebTestCase with auth.
 */
final class ImportControllerDedupTest extends KernelTestCase
{
    public function testWasAlreadyImportedReturnsFalseForNewHash(): void
    {
        self::bootKernel();
        $storage = self::getContainer()->get(ImportStoragePort::class);
        assert($storage instanceof ImportStoragePort);

        $userId = UserId::generate();

        self::assertFalse($storage->wasAlreadyImported($userId, 'new-hash-never-seen'));
    }

    public function testWasAlreadyImportedReturnsTrueAfterStore(): void
    {
        self::bootKernel();
        $storage = self::getContainer()->get(ImportStoragePort::class);
        assert($storage instanceof ImportStoragePort);

        $userId = UserId::generate();
        $hash = hash('sha256', 'test-csv-content-dedup');

        // Create a minimal NormalizedTransaction for the store call
        $tx = new \App\BrokerImport\Application\DTO\NormalizedTransaction(
            id: \App\Shared\Domain\ValueObject\TransactionId::generate(),
            isin: \App\Shared\Domain\ValueObject\ISIN::fromString('US0378331005'),
            symbol: 'AAPL',
            type: \App\BrokerImport\Application\DTO\TransactionType::BUY,
            date: new \DateTimeImmutable('2025-06-15'),
            quantity: \Brick\Math\BigDecimal::of('10'),
            pricePerUnit: \App\Shared\Domain\ValueObject\Money::of('150.00', \App\Shared\Domain\ValueObject\CurrencyCode::USD),
            commission: \App\Shared\Domain\ValueObject\Money::of('1.00', \App\Shared\Domain\ValueObject\CurrencyCode::USD),
            broker: \App\Shared\Domain\ValueObject\BrokerId::of('ibkr'),
            description: 'Test',
            rawData: [],
        );

        $storage->store($userId, BrokerId::of('ibkr'), [$tx], $hash);

        self::assertTrue($storage->wasAlreadyImported($userId, $hash));
    }
}
