<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine;

use App\Shared\Infrastructure\Doctrine\Type\EncryptedStringType;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Initializes EncryptedStringType with ENCRYPTION_KEY from environment.
 * Runs once per kernel boot (first request).
 */
#[AsEventListener(event: 'kernel.request', priority: 1024)]
final readonly class EncryptionKeyInitializer
{
    public function __construct(
        private string $encryptionKey,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        EncryptedStringType::setEncryptionKey($this->encryptionKey);
    }
}
