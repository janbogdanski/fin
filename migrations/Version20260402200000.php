<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * US-S8-01: Add profile fields (nip, first_name, last_name) to users table.
 */
final class Version20260402200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nip, first_name, last_name columns to users table for user profile';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD COLUMN nip VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD COLUMN first_name VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD COLUMN last_name VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS last_name');
        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS first_name');
        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS nip');
    }
}
