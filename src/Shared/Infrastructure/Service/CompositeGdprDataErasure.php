<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Service;

use App\Shared\Domain\Port\GdprDataErasurePort;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Chains multiple GdprDataErasurePort implementations.
 *
 * All deletions execute within the caller's transaction (AnonymizeUserHandler::transactional),
 * so partial failure rolls back the entire erasure atomically.
 */
final readonly class CompositeGdprDataErasure implements GdprDataErasurePort
{
    /**
     * @param iterable<GdprDataErasurePort> $chain
     */
    public function __construct(
        private iterable $chain
    ) {
    }

    public function deleteByUser(UserId $userId): void
    {
        foreach ($this->chain as $eraser) {
            $eraser->deleteByUser($userId);
        }
    }
}
