<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Twig;

use App\Billing\Application\Port\PaymentRepositoryPort;
use App\Billing\Domain\ValueObject\ProductCode;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\ValueObject\UserId;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Provides user subscription label for nav bar display.
 * Lazily evaluated — only queries the DB when the function is called in Twig.
 */
final class UserSubscriptionExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly PaymentRepositoryPort $paymentRepository,
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('user_subscription_label', $this->getSubscriptionLabel(...)),
        ];
    }

    public function getSubscriptionLabel(): string
    {
        $user = $this->security->getUser();

        if (! $user instanceof SecurityUser) {
            return '';
        }

        $userId = UserId::fromString($user->id());

        if ($this->paymentRepository->hasActivePaymentForTier($userId, ProductCode::PRO)) {
            return 'Pro';
        }

        if ($this->paymentRepository->hasActivePaymentForTier($userId, ProductCode::STANDARD)) {
            return 'Standard';
        }

        return 'Free';
    }
}
