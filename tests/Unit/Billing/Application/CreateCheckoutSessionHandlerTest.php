<?php

declare(strict_types=1);

namespace App\Tests\Unit\Billing\Application;

use App\Billing\Application\Command\CreateCheckoutSession;
use App\Billing\Application\Command\CreateCheckoutSessionHandler;
use App\Billing\Application\Dto\CheckoutSessionResult;
use App\Billing\Application\Port\PaymentGatewayPort;
use App\Billing\Application\Port\PaymentRepositoryPort;
use App\Billing\Domain\Model\Payment;
use App\Billing\Domain\ValueObject\ProductCode;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CreateCheckoutSessionHandlerTest extends TestCase
{
    private PaymentGatewayPort&MockObject $gateway;

    private PaymentRepositoryPort&MockObject $repository;

    private CreateCheckoutSessionHandler $handler;

    protected function setUp(): void
    {
        $this->gateway = $this->createMock(PaymentGatewayPort::class);
        $this->repository = $this->createMock(PaymentRepositoryPort::class);
        $this->handler = new CreateCheckoutSessionHandler($this->gateway, $this->repository);
    }

    public function testCreatesSessionWithCorrectAmountAndPersistsPayment(): void
    {
        $userId = UserId::generate();
        $productCode = ProductCode::STANDARD;

        $this->gateway
            ->expects(self::once())
            ->method('createCheckoutSession')
            ->with(
                $userId,
                $productCode,
                'https://example.com/success',
                'https://example.com/cancel',
            )
            ->willReturn(new CheckoutSessionResult(
                sessionId: 'cs_test_123',
                checkoutUrl: 'https://checkout.stripe.com/pay/cs_test_123',
            ));

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (Payment $payment) use ($userId): bool {
                return $payment->userId()->equals($userId)
                    && $payment->providerSessionId() === 'cs_test_123'
                    && $payment->productCode() === ProductCode::STANDARD
                    && $payment->amountCents() === 9900
                    && $payment->currency() === 'PLN';
            }));

        $this->repository
            ->expects(self::once())
            ->method('flush');

        $result = ($this->handler)(new CreateCheckoutSession(
            userId: $userId,
            productCode: $productCode,
            successUrl: 'https://example.com/success',
            cancelUrl: 'https://example.com/cancel',
        ));

        self::assertSame('https://checkout.stripe.com/pay/cs_test_123', $result->checkoutUrl);
    }

    public function testCreatesProSessionWithCorrectAmount(): void
    {
        $userId = UserId::generate();

        $this->gateway
            ->method('createCheckoutSession')
            ->willReturn(new CheckoutSessionResult(
                sessionId: 'cs_test_pro_456',
                checkoutUrl: 'https://checkout.stripe.com/pay/cs_test_pro_456',
            ));

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (Payment $payment): bool => $payment->amountCents() === 19900
                && $payment->productCode() === ProductCode::PRO));

        $this->repository->expects(self::once())->method('flush');

        ($this->handler)(new CreateCheckoutSession(
            userId: $userId,
            productCode: ProductCode::PRO,
            successUrl: 'https://example.com/success',
            cancelUrl: 'https://example.com/cancel',
        ));
    }
}
