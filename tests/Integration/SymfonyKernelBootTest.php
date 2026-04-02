<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\TaxCalc\Domain\Repository\TaxPositionLedgerRepositoryInterface;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SymfonyKernelBootTest extends KernelTestCase
{
    public function testKernelBoots(): void
    {
        self::bootKernel();

        self::assertNotNull(self::$kernel);
        self::assertSame('test', self::$kernel->getEnvironment());
    }

    public function testDoctrineConnectionIsRegistered(): void
    {
        self::bootKernel();

        $connection = self::getContainer()->get(Connection::class);

        self::assertInstanceOf(Connection::class, $connection);
    }

    public function testContainerHasUserRepository(): void
    {
        self::bootKernel();

        $repository = self::getContainer()->get(UserRepositoryInterface::class);

        self::assertInstanceOf(UserRepositoryInterface::class, $repository);
    }

    public function testContainerHasTaxPositionLedgerRepository(): void
    {
        self::bootKernel();

        $repository = self::getContainer()->get(TaxPositionLedgerRepositoryInterface::class);

        self::assertInstanceOf(TaxPositionLedgerRepositoryInterface::class, $repository);
    }
}
