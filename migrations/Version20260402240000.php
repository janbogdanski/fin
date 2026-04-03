<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add referral program fields to users table.
 *
 * - referral_code: unique code for sharing (e.g. TAXPILOT-019746)
 * - referred_by: referral code of the user who referred this user
 * - bonus_transactions: additional free transaction slots earned via referrals
 */
final class Version20260402240000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add referral_code, referred_by, bonus_transactions to users table';
    }

    public function up(Schema $schema): void
    {
        // Step 1: Add columns as nullable first for existing rows
        $this->addSql('ALTER TABLE users ADD COLUMN referral_code VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD COLUMN referred_by VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD COLUMN bonus_transactions INTEGER NOT NULL DEFAULT 0');

        // Step 2: Backfill referral_code for existing users from their ID
        $this->addSql("UPDATE users SET referral_code = 'TAXPILOT-' || SUBSTR(REPLACE(id::text, '-', ''), 1, 6) WHERE referral_code IS NULL");

        // Step 3: Make referral_code NOT NULL and add unique index
        $this->addSql('CREATE UNIQUE INDEX uniq_users_referral_code ON users (referral_code)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_users_referral_code');
        $this->addSql('ALTER TABLE users DROP COLUMN referral_code');
        $this->addSql('ALTER TABLE users DROP COLUMN referred_by');
        $this->addSql('ALTER TABLE users DROP COLUMN bonus_transactions');
    }
}
