<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * US-S8-02: Dividend persistence -- dividend_tax_results table.
 *
 * Stores computed dividend tax results per user/year/country.
 * Enables real data in dashboard, PIT-38 preview, and DividendResultQueryPort.
 */
final class Version20260402220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create dividend_tax_results table for persisting computed dividend tax calculations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE dividend_tax_results (
                id SERIAL NOT NULL,
                user_id UUID NOT NULL,
                tax_year INTEGER NOT NULL,
                country_code VARCHAR(2) NOT NULL,
                gross_pln NUMERIC(19,8) NOT NULL,
                wht_pln NUMERIC(19,8) NOT NULL,
                tax_due_pln NUMERIC(19,8) NOT NULL,
                wht_rate NUMERIC(10,6) NOT NULL,
                upo_rate NUMERIC(10,6) NOT NULL,
                nbp_rate_date DATE NOT NULL,
                nbp_rate_value NUMERIC(19,8) NOT NULL,
                nbp_rate_table VARCHAR(20) NOT NULL,
                nbp_rate_currency VARCHAR(3) NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql('CREATE INDEX idx_dividend_results_user_year ON dividend_tax_results (user_id, tax_year)');
        $this->addSql('CREATE INDEX idx_dividend_results_user_year_country ON dividend_tax_results (user_id, tax_year, country_code)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS dividend_tax_results');
    }
}
