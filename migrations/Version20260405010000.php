<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * P2-107: Persistent audit log table.
 *
 * Records security events (login, logout, failed auth, PII access, account deletion).
 * user_id is nullable to support pre-authentication events.
 * No FK on user_id — audit log must survive user anonymization.
 */
final class Version20260405010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'P2-107: create audit_log table for persistent security event trail';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE audit_log (
                id BIGSERIAL PRIMARY KEY,
                user_id UUID NULL,
                event_type VARCHAR(100) NOT NULL,
                context JSONB NOT NULL DEFAULT '{}',
                ip_address INET NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS audit_log');
    }
}
