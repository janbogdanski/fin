<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * P2-106: Snapshot table for finalized tax calculations.
 *
 * Stores a point-in-time record of every XML export so that the numbers
 * used in the XML can be audited even after calculation logic changes.
 * The xml_sha256 column lets us verify the XML has not been tampered with.
 */
final class Version20260405000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'P2-106: create tax_calculation_snapshots table for audit trail';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE tax_calculation_snapshots (
                id UUID NOT NULL,
                user_id UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
                tax_year INT NOT NULL,
                generated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                equity_gain_loss NUMERIC(15,2) NOT NULL,
                equity_tax_base NUMERIC(15,2) NOT NULL,
                equity_tax_due NUMERIC(15,2) NOT NULL,
                prior_losses_applied NUMERIC(15,2) NOT NULL DEFAULT 0,
                dividend_income NUMERIC(15,2) NOT NULL DEFAULT 0,
                dividend_tax_due NUMERIC(15,2) NOT NULL DEFAULT 0,
                xml_sha256 VARCHAR(64) NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS tax_calculation_snapshots');
    }
}
