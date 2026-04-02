<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Infrastructure\Doctrine;

use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\NBPRate;
use App\Shared\Domain\ValueObject\TransactionId;
use App\TaxCalc\Domain\Model\ClosedPosition;
use App\TaxCalc\Infrastructure\Doctrine\ClosedPositionImmutabilityListener;
use Brick\Math\BigDecimal;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use PHPUnit\Framework\TestCase;

final class ClosedPositionImmutabilityListenerTest extends TestCase
{
    private ClosedPositionImmutabilityListener $listener;

    protected function setUp(): void
    {
        $this->listener = new ClosedPositionImmutabilityListener();
    }

    public function testPreUpdateThrowsForClosedPosition(): void
    {
        $entity = $this->buildClosedPosition();
        $em = $this->createMock(EntityManagerInterface::class);
        $changeSet = [];

        $args = new PreUpdateEventArgs($entity, $em, $changeSet);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('ClosedPosition is append-only');

        $this->listener->preUpdate($args);
    }

    public function testPreRemoveThrowsForClosedPosition(): void
    {
        $entity = $this->buildClosedPosition();
        $em = $this->createMock(EntityManagerInterface::class);

        $args = new PreRemoveEventArgs($entity, $em);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('ClosedPosition is append-only');

        $this->listener->preRemove($args);
    }

    public function testPreUpdateIgnoresOtherEntities(): void
    {
        $entity = new \stdClass();
        $em = $this->createMock(EntityManagerInterface::class);
        $changeSet = [];

        $args = new PreUpdateEventArgs($entity, $em, $changeSet);

        $this->listener->preUpdate($args);
        self::assertTrue(true);
    }

    public function testPreRemoveIgnoresOtherEntities(): void
    {
        $entity = new \stdClass();
        $em = $this->createMock(EntityManagerInterface::class);

        $args = new PreRemoveEventArgs($entity, $em);

        $this->listener->preRemove($args);
        self::assertTrue(true);
    }

    private function buildClosedPosition(): ClosedPosition
    {
        $nbpRate = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('4.0500'),
            new \DateTimeImmutable('2024-03-14'),
            '052/A/NBP/2024',
        );

        return new ClosedPosition(
            buyTransactionId: TransactionId::generate(),
            sellTransactionId: TransactionId::generate(),
            isin: ISIN::fromString('US0378331005'),
            quantity: BigDecimal::of('10'),
            costBasisPLN: BigDecimal::of('6930.00'),
            proceedsPLN: BigDecimal::of('7500.00'),
            buyCommissionPLN: BigDecimal::of('4.05'),
            sellCommissionPLN: BigDecimal::of('5.06'),
            gainLossPLN: BigDecimal::of('560.89'),
            buyDate: new \DateTimeImmutable('2024-03-15'),
            sellDate: new \DateTimeImmutable('2024-09-10'),
            buyNBPRate: $nbpRate,
            sellNBPRate: $nbpRate,
            buyBroker: BrokerId::of('ibkr'),
            sellBroker: BrokerId::of('ibkr'),
        );
    }
}
