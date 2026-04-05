<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Audit;

use App\Shared\Infrastructure\Audit\AuditLogger;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for AuditLogger.
 *
 * Verifies that log() calls Connection::insert() with the correct event_type
 * and that nullable fields are forwarded as-is.
 */
final class AuditLoggerTest extends TestCase
{
    public function test_log_calls_insert_with_correct_event_type(): void
    {
        $connection = $this->createMock(Connection::class);

        $connection
            ->expects(self::once())
            ->method('insert')
            ->with(
                'audit_log',
                self::callback(static function (array $data): bool {
                    return $data['event_type'] === 'user.anonymized';
                }),
            );

        $logger = new AuditLogger($connection);
        $logger->log('user.anonymized', 'some-user-id');
    }

    public function test_log_includes_user_id_when_provided(): void
    {
        $connection = $this->createMock(Connection::class);

        $connection
            ->expects(self::once())
            ->method('insert')
            ->with(
                'audit_log',
                self::callback(static function (array $data): bool {
                    return $data['user_id'] === 'abc-123';
                }),
            );

        $logger = new AuditLogger($connection);
        $logger->log('user.anonymized', 'abc-123');
    }

    public function test_log_accepts_null_user_id_for_pre_auth_events(): void
    {
        $connection = $this->createMock(Connection::class);

        $connection
            ->expects(self::once())
            ->method('insert')
            ->with(
                'audit_log',
                self::callback(static function (array $data): bool {
                    return $data['user_id'] === null;
                }),
            );

        $logger = new AuditLogger($connection);
        $logger->log('auth.failed', null, ['email' => 'x@example.com'], '1.2.3.4');
    }

    public function test_log_encodes_context_as_json(): void
    {
        $connection = $this->createMock(Connection::class);

        $connection
            ->expects(self::once())
            ->method('insert')
            ->with(
                'audit_log',
                self::callback(static function (array $data): bool {
                    $decoded = json_decode($data['context'], true);

                    return $decoded === ['foo' => 'bar'];
                }),
            );

        $logger = new AuditLogger($connection);
        $logger->log('some.event', null, ['foo' => 'bar']);
    }

    public function test_log_forwards_ip_address(): void
    {
        $connection = $this->createMock(Connection::class);

        $connection
            ->expects(self::once())
            ->method('insert')
            ->with(
                'audit_log',
                self::callback(static function (array $data): bool {
                    return $data['ip_address'] === '192.168.1.100';
                }),
            );

        $logger = new AuditLogger($connection);
        $logger->log('user.anonymized', 'uid-1', [], '192.168.1.100');
    }
}
