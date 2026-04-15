<?php

declare(strict_types=1);

namespace App\Shared\Domain\Port;

use App\Shared\Domain\ValueObject\UserId;

/**
 * Cross-cutting port for GDPR art. 17 right-to-erasure.
 *
 * Each module that stores user data implements this port.
 * AnonymizeUserHandler collects all implementations and calls them
 * atomically inside a single transaction.
 */
interface GdprDataErasurePort
{
    public function deleteByUser(UserId $userId): void;
}
