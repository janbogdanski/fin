<?php

declare(strict_types=1);

namespace App\Shared\Domain\Port;

interface AuditLogPort
{
    /**
     * @param array<string, mixed> $context
     */
    public function log(
        string $eventType,
        ?string $userId,
        array $context = [],
        ?string $ipAddress = null,
    ): void;
}
