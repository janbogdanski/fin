<?php

declare(strict_types=1);

namespace App\Tests\Unit\Billing\Application;

use App\Billing\Application\Command\HandleStripeWebhook;
use App\Billing\Application\Command\HandleStripeWebhookHandler;
use App\Billing\Application\Port\PaymentRepositoryPort;
use App\Billing\Domain\Model\Payment;
use App\Billing\Domain\ValueObject\PaymentStatus;
use App\Billing\Domain\ValueObject\ProductCode;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class HandleStripeWebhookHandlerTest extends TestCase
{
    private PaymentRepositoryPort&MockObject $repository;

    private HandleStripeWebhookHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PaymentRepositoryPort::class);
        $this->handler = new HandleStripeWebhookHandler($this->repository);
    }

    public function testMarksPaymentAsPaidWhenSessionFound(): void
    {
        $payment = Payment::create(
            userId: UserId::generate(),
            stripeSessionId: 'cs_test_123',
            productCode: ProductCode::STANDARD,
        );

        $this->repository
            ->method('findByStripeSessionId')
            ->with('cs_test_123')
            ->willReturn($payment);

        $this->repository->expects(self::once())->method('save')->with($payment);
        $this->repository->expects(self::once())->method('flush');

        ($this->handler)(new HandleStripeWebhook(
            stripeSessionId: 'cs_test_123',
            stripePaymentIntentId: 'pi_test_abc',
        ));

        self::assertSame(PaymentStatus::PAID, $payment->status());
        self::assertSame('pi_test_abc', $payment->stripePaymentIntentId());
        self::assertTrue($payment->isPaid());
    }

    public function testIgnoresUnknownSession(): void
    {
        $this->repository
            ->method('findByStripeSessionId')
            ->willReturn(null);

        $this->repository->expects(self::never())->method('save');
        $this->repository->expects(self::never())->method('flush');

        ($this->handler)(new HandleStripeWebhook(
            stripeSessionId: 'cs_unknown',
            stripePaymentIntentId: 'pi_test_xyz',
        ));
    }
}
