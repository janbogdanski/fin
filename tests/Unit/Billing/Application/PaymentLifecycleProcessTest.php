<?php

declare(strict_types=1);

namespace App\Tests\Unit\Billing\Application;

use App\Billing\Application\Command\CreateCheckoutSession;
use App\Billing\Application\Command\CreateCheckoutSessionHandler;
use App\Billing\Application\Command\HandlePaymentWebhook;
use App\Billing\Application\Command\HandlePaymentWebhookHandler;
use App\Billing\Domain\ValueObject\ProductCode;
use App\Shared\Domain\ValueObject\UserId;
use App\Tests\InMemory\InMemoryPaymentGatewayAdapter;
use App\Tests\InMemory\InMemoryPaymentRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;

final class PaymentLifecycleProcessTest extends TestCase
{
    private InMemoryPaymentRepository $payments;

    private CreateCheckoutSessionHandler $createCheckoutSession;

    private HandlePaymentWebhookHandler $handleWebhook;

    protected function setUp(): void
    {
        $this->payments = new InMemoryPaymentRepository();
        $this->createCheckoutSession = new CreateCheckoutSessionHandler(
            new InMemoryPaymentGatewayAdapter(),
            $this->payments,
            new MockClock(new \DateTimeImmutable('2026-04-08 10:00:00')),
        );
        $this->handleWebhook = new HandlePaymentWebhookHandler($this->payments, new NullLogger());
    }

    public function testCheckoutThenWebhookUnlocksPurchasedTier(): void
    {
        $userId = UserId::generate();
        $result = ($this->createCheckoutSession)(new CreateCheckoutSession(
            userId: $userId,
            productCode: ProductCode::PRO,
            successUrl: 'https://taxpilot.test/billing/success',
            cancelUrl: 'https://taxpilot.test/billing/cancel',
        ));

        $pendingPayment = $this->payments->findByProviderSessionId(InMemoryPaymentGatewayAdapter::FAKE_SESSION_ID);

        self::assertSame(InMemoryPaymentGatewayAdapter::FAKE_CHECKOUT_URL, $result->checkoutUrl);
        self::assertNotNull($pendingPayment);
        self::assertFalse($pendingPayment->isPaid());

        ($this->handleWebhook)(new HandlePaymentWebhook(
            providerSessionId: InMemoryPaymentGatewayAdapter::FAKE_SESSION_ID,
            providerTransactionId: 'pi_paid_123',
        ));

        $paidPayment = $this->payments->findByProviderSessionId(InMemoryPaymentGatewayAdapter::FAKE_SESSION_ID);

        self::assertNotNull($paidPayment);
        self::assertTrue($paidPayment->isPaid());
        self::assertSame('pi_paid_123', $paidPayment->providerTransactionId());
        self::assertTrue($this->payments->hasActivePaymentForTier($userId, ProductCode::STANDARD));
        self::assertTrue($this->payments->hasActivePaymentForTier($userId, ProductCode::PRO));
    }

    public function testDuplicateWebhookKeepsPaymentPaidWithoutChangingOriginalTransactionId(): void
    {
        $userId = UserId::generate();
        ($this->createCheckoutSession)(new CreateCheckoutSession(
            userId: $userId,
            productCode: ProductCode::STANDARD,
            successUrl: 'https://taxpilot.test/billing/success',
            cancelUrl: 'https://taxpilot.test/billing/cancel',
        ));

        ($this->handleWebhook)(new HandlePaymentWebhook(
            providerSessionId: InMemoryPaymentGatewayAdapter::FAKE_SESSION_ID,
            providerTransactionId: 'pi_paid_123',
        ));
        ($this->handleWebhook)(new HandlePaymentWebhook(
            providerSessionId: InMemoryPaymentGatewayAdapter::FAKE_SESSION_ID,
            providerTransactionId: 'pi_paid_duplicate',
        ));

        $payment = $this->payments->findByProviderSessionId(InMemoryPaymentGatewayAdapter::FAKE_SESSION_ID);

        self::assertNotNull($payment);
        self::assertTrue($payment->isPaid());
        self::assertSame('pi_paid_123', $payment->providerTransactionId());
        self::assertTrue($this->payments->hasActivePaymentForTier($userId, ProductCode::STANDARD));
    }
}
