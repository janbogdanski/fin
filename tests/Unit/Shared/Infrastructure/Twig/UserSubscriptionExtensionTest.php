<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Twig;

use App\Billing\Application\Port\PaymentRepositoryPort;
use App\Billing\Domain\ValueObject\ProductCode;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Infrastructure\Twig\UserSubscriptionExtension;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\TwigFunction;

final class UserSubscriptionExtensionTest extends TestCase
{
    private Security&MockObject $security;

    private PaymentRepositoryPort&MockObject $paymentRepository;

    private UserSubscriptionExtension $extension;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->paymentRepository = $this->createMock(PaymentRepositoryPort::class);
        $this->extension = new UserSubscriptionExtension($this->security, $this->paymentRepository);
    }

    public function testGetFunctionsRegistersTwigFunction(): void
    {
        $functions = $this->extension->getFunctions();

        self::assertCount(1, $functions);
        self::assertInstanceOf(TwigFunction::class, $functions[0]);
        self::assertSame('user_subscription_label', $functions[0]->getName());
    }

    public function testReturnsEmptyStringWhenUserIsNotSecurityUser(): void
    {
        $this->security->method('getUser')->willReturn(null);

        self::assertSame('', $this->extension->getSubscriptionLabel());
    }

    public function testReturnsProLabelWhenUserHasProSubscription(): void
    {
        $user = new SecurityUser('019746a0-1234-7000-8000-000000000001', 'jan@example.com');
        $this->security->method('getUser')->willReturn($user);

        $this->paymentRepository->method('hasActivePaymentForTier')
            ->willReturnCallback(static fn ($userId, ProductCode $code): bool => $code === ProductCode::PRO);

        self::assertSame('Pro', $this->extension->getSubscriptionLabel());
    }

    public function testReturnsStandardLabelWhenUserHasStandardSubscription(): void
    {
        $user = new SecurityUser('019746a0-1234-7000-8000-000000000001', 'jan@example.com');
        $this->security->method('getUser')->willReturn($user);

        $this->paymentRepository->method('hasActivePaymentForTier')
            ->willReturnCallback(static fn ($userId, ProductCode $code): bool => $code === ProductCode::STANDARD);

        self::assertSame('Standard', $this->extension->getSubscriptionLabel());
    }

    public function testReturnsFreeWhenUserHasNoSubscription(): void
    {
        $user = new SecurityUser('019746a0-1234-7000-8000-000000000001', 'jan@example.com');
        $this->security->method('getUser')->willReturn($user);

        $this->paymentRepository->method('hasActivePaymentForTier')->willReturn(false);

        self::assertSame('Free', $this->extension->getSubscriptionLabel());
    }
}
