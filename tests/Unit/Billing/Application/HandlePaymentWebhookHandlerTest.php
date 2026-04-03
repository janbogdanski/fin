<?php

declare(strict_types=1);

namespace App\Tests\Unit\Billing\Application;

use App\Billing\Application\Command\HandlePaymentWebhook;
use App\Billing\Application\Command\HandlePaymentWebhookHandler;
use App\Billing\Application\Port\PaymentRepositoryPort;
use App\Billing\Domain\Model\Payment;
use App\Billing\Domain\ValueObject\PaymentStatus;
use App\Billing\Domain\ValueObject\ProductCode;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class HandlePaymentWebhookHandlerTest extends TestCase
{
    private PaymentRepositoryPort&MockObject $repository;

    private HandlePaymentWebhookHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PaymentRepositoryPort::class);
        $this->handler = new HandlePaymentWebhookHandler($this->repository, new NullLogger());
    }

    public function testMarksPaymentAsPaidWhenSessionFound(): void
    {
        $payment = Payment::create(
            userId: UserId::generate(),
            providerSessionId: 'cs_test_123',
            productCode: ProductCode::STANDARD,
            createdAt: new \DateTimeImmutable('2025-01-15 10:00:00'),
        );

        $this->repository
            ->method('findByProviderSessionId')
            ->with('cs_test_123')
            ->willReturn($payment);

        $this->repository->expects(self::once())->method('save')->with($payment);
        $this->repository->expects(self::once())->method('flush');

        ($this->handler)(new HandlePaymentWebhook(
            providerSessionId: 'cs_test_123',
            providerTransactionId: 'pi_test_abc',
        ));

        self::assertSame(PaymentStatus::PAID, $payment->status());
        self::assertSame('pi_test_abc', $payment->providerTransactionId());
        self::assertTrue($payment->isPaid());
    }

    public function testIgnoresUnknownSession(): void
    {
        $this->repository
            ->method('findByProviderSessionId')
            ->willReturn(null);

        $this->repository->expects(self::never())->method('save');
        $this->repository->expects(self::never())->method('flush');

        ($this->handler)(new HandlePaymentWebhook(
            providerSessionId: 'cs_unknown',
            providerTransactionId: 'pi_test_xyz',
        ));
    }
}
