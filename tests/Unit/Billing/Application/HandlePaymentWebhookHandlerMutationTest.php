<?php

declare(strict_types=1);

namespace App\Tests\Unit\Billing\Application;

use App\Billing\Application\Command\HandlePaymentWebhook;
use App\Billing\Application\Command\HandlePaymentWebhookHandler;
use App\Billing\Application\Port\PaymentRepositoryPort;
use App\Billing\Domain\Model\Payment;
use App\Billing\Domain\ValueObject\ProductCode;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Mutation-killing tests for HandlePaymentWebhookHandler.
 * Targets: logger call removals and array key mutations.
 */
final class HandlePaymentWebhookHandlerMutationTest extends TestCase
{
    private PaymentRepositoryPort&MockObject $repository;

    private LoggerInterface&MockObject $logger;

    private HandlePaymentWebhookHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PaymentRepositoryPort::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->handler = new HandlePaymentWebhookHandler($this->repository, $this->logger);
    }

    /**
     * Kills mutants #1-#3: logger->warning() must be called with correct context
     * when session is unknown.
     */
    public function testLogsWarningWithContextForUnknownSession(): void
    {
        $this->repository
            ->method('findByProviderSessionId')
            ->willReturn(null);

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Webhook received for unknown session.',
                self::callback(function (array $context): bool {
                    return isset($context['providerSessionId'])
                        && $context['providerSessionId'] === 'cs_unknown_123';
                }),
            );

        ($this->handler)(new HandlePaymentWebhook(
            providerSessionId: 'cs_unknown_123',
            providerTransactionId: 'pi_test_xyz',
        ));
    }

    /**
     * Kills mutants #4-#7: logger->info() must be called with paymentId and providerSessionId
     * when payment is successfully marked as paid.
     */
    public function testLogsInfoWithContextForSuccessfulPayment(): void
    {
        $payment = Payment::create(
            userId: UserId::generate(),
            providerSessionId: 'cs_test_456',
            productCode: ProductCode::STANDARD,
        );

        $this->repository
            ->method('findByProviderSessionId')
            ->with('cs_test_456')
            ->willReturn($payment);

        $this->repository->expects(self::once())->method('save');
        $this->repository->expects(self::once())->method('flush');

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Payment marked as paid.',
                self::callback(function (array $context): bool {
                    return isset($context['paymentId'])
                        && isset($context['providerSessionId'])
                        && $context['providerSessionId'] === 'cs_test_456';
                }),
            );

        ($this->handler)(new HandlePaymentWebhook(
            providerSessionId: 'cs_test_456',
            providerTransactionId: 'pi_test_def',
        ));
    }
}
