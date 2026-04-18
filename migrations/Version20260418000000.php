<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds pesel column to users table.
 * Persons without business activity use PESEL (11 digits) instead of NIP.
 * Column uses encrypted_string type (VARCHAR 255) — same as nip.
 */
final class Version20260418000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pesel column to users table (alternative to NIP for natural persons)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD COLUMN pesel VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN pesel');
    }
}
