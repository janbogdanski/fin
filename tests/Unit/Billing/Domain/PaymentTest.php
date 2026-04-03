<?php

declare(strict_types=1);

namespace App\Tests\Unit\Billing\Domain;

use App\Billing\Domain\Model\Payment;
use App\Billing\Domain\ValueObject\PaymentStatus;
use App\Billing\Domain\ValueObject\ProductCode;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class PaymentTest extends TestCase
{
    public function testMarkAsPaidTransitionsPendingToPaid(): void
    {
        $payment = Payment::create(
            userId: UserId::generate(),
            providerSessionId: 'session_1',
            productCode: ProductCode::STANDARD,
            createdAt: new \DateTimeImmutable('2025-01-15 10:00:00'),
        );

        $payment->markAsPaid('tx_123');

        self::assertSame(PaymentStatus::PAID, $payment->status());
        self::assertSame('tx_123', $payment->providerTransactionId());
    }

    public function testMarkAsPaidIsIdempotentWhenAlreadyPaid(): void
    {
        $payment = Payment::create(
            userId: UserId::generate(),
            providerSessionId: 'session_1',
            productCode: ProductCode::STANDARD,
            createdAt: new \DateTimeImmutable('2025-01-15 10:00:00'),
        );

        $payment->markAsPaid('tx_123');
        $payment->markAsPaid('tx_456'); // second call is silently ignored

        self::assertSame(PaymentStatus::PAID, $payment->status());
        self::assertSame('tx_123', $payment->providerTransactionId());
    }

    public function testMarkAsPaidThrowsWhenPaymentHasFailed(): void
    {
        $payment = Payment::create(
            userId: UserId::generate(),
            providerSessionId: 'session_1',
            productCode: ProductCode::STANDARD,
            createdAt: new \DateTimeImmutable('2025-01-15 10:00:00'),
        );

        $payment->markAsFailed();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/Cannot mark payment .* as paid/');

        $payment->markAsPaid('tx_123');
    }
}
