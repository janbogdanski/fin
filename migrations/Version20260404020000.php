<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * P0-012: GDPR art. 17 — right to erasure.
 *
 * Adds `anonymized_at` column to the `users` table.
 * NULL means the account is active; a timestamp means it has been anonymized.
 */
final class Version20260404020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GDPR art. 17: add anonymized_at column to users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD COLUMN anonymized_at TIMESTAMP(0) WITHOUT TIME ZONE NULL DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS anonymized_at');
    }
}
