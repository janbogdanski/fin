<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Stores broker export files submitted automatically when the format is unrecognized.
 * Used to prioritize new broker adapter implementation during beta.
 */
final class Version20260414000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create broker_adapter_request table for unrecognized broker file submissions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE broker_adapter_request (
                id            VARCHAR(36)  NOT NULL,
                user_id       VARCHAR(36)  NOT NULL,
                filename      VARCHAR(500) NOT NULL,
                file_content  BYTEA        NOT NULL,
                file_size     INTEGER      NOT NULL,
                status        VARCHAR(20)  NOT NULL DEFAULT 'pending',
                created_at    TIMESTAMP    NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql('CREATE INDEX idx_broker_adapter_request_user ON broker_adapter_request (user_id)');
        $this->addSql('CREATE INDEX idx_broker_adapter_request_status ON broker_adapter_request (status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE broker_adapter_request');
    }
}
