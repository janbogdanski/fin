<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add magic link token fields to users table.
 */
final class Version20260402170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add login_token and login_token_expires_at columns to users table for magic link authentication.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD login_token VARCHAR(128) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD login_token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_users_login_token ON users (login_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_users_login_token');
        $this->addSql('ALTER TABLE users DROP login_token');
        $this->addSql('ALTER TABLE users DROP login_token_expires_at');
    }
}
