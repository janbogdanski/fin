<?php

declare(strict_types=1);

namespace App\Tests\Chaos;

use App\Billing\Application\Command\CreateCheckoutSession;
use App\Billing\Application\Command\CreateCheckoutSessionHandler;
use App\Billing\Application\Port\PaymentGatewayPort;
use App\Billing\Application\Port\PaymentRepositoryPort;
use App\Billing\Domain\ValueObject\ProductCode;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

/**
 * @group chaos
 *
 * Simulates payment gateway infrastructure failures:
 * - Gateway throws RuntimeException → exception propagates (no silent failure)
 * - Gateway throws before session ID is available → no Payment entity is persisted (atomicity)
 */
final class PaymentGatewayFailureTest extends TestCase
{
    public function testGatewayRuntimeExceptionPropagatesFromHandler(): void
    {
        $gateway = $this->createMock(PaymentGatewayPort::class);
        $gateway->method('createCheckoutSession')
            ->willThrowException(new \RuntimeException('Stripe API unreachable'));

        $repository = $this->createMock(PaymentRepositoryPort::class);

        $clock = $this->createMock(ClockInterface::class);

        $handler = new CreateCheckoutSessionHandler($gateway, $repository, $clock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stripe API unreachable');

        ($handler)(new CreateCheckoutSession(
            userId: UserId::generate(),
            productCode: ProductCode::STANDARD,
            successUrl: 'https://example.com/success',
            cancelUrl: 'https://example.com/cancel',
        ));
    }

    public function testNoPaymentPersistedWhenGatewayThrows(): void
    {
        $gateway = $this->createMock(PaymentGatewayPort::class);
        $gateway->method('createCheckoutSession')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $repository = $this->createMock(PaymentRepositoryPort::class);
        // save() must NEVER be called if the gateway throws before returning a session
        $repository->expects(self::never())->method('save');
        $repository->expects(self::never())->method('flush');

        $clock = $this->createMock(ClockInterface::class);

        $handler = new CreateCheckoutSessionHandler($gateway, $repository, $clock);

        try {
            ($handler)(new CreateCheckoutSession(
                userId: UserId::generate(),
                productCode: ProductCode::STANDARD,
                successUrl: 'https://example.com/success',
                cancelUrl: 'https://example.com/cancel',
            ));
            self::fail('Expected RuntimeException to be thrown');
        } catch (\RuntimeException) {
            // Exception propagated — assertions on repository mock are verified by PHPUnit
        }
    }
}
