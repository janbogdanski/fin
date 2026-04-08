<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Audit;

use App\Shared\Domain\Port\AuditLogPort;
use Doctrine\DBAL\Connection;

/**
 * Persists security and compliance events to the audit_log table.
 *
 * user_id is nullable to support pre-authentication events (e.g. failed login).
 * No FK on user_id — this table must survive user anonymization (GDPR art. 17).
 */
final class AuditLogger implements AuditLogPort
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log(
        string $eventType,
        ?string $userId,
        array $context = [],
        ?string $ipAddress = null,
    ): void {
        $this->connection->insert('audit_log', [
            'user_id' => $userId,
            'event_type' => $eventType,
            'context' => json_encode($context, JSON_THROW_ON_ERROR),
            'ip_address' => $ipAddress,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}
