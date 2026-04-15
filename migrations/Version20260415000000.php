<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds expires_at to broker_adapter_request for GDPR Art. 5(1)(e) storage limitation.
 * Records are retained for 90 days; a purge command deletes expired rows.
 */
final class Version20260415000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add expires_at column to broker_adapter_request (90-day GDPR retention TTL)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE broker_adapter_request ADD COLUMN expires_at TIMESTAMP NOT NULL DEFAULT (NOW() + INTERVAL '90 days')");
        $this->addSql('CREATE INDEX idx_broker_adapter_request_expires ON broker_adapter_request (expires_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_broker_adapter_request_expires');
        $this->addSql('ALTER TABLE broker_adapter_request DROP COLUMN expires_at');
    }
}
