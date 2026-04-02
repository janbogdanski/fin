<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sprint 3 — initial schema: users, tax_position_ledgers, open_positions, closed_positions.
 */
final class Version20260402000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create core tables: users, tax_position_ledgers, open_positions, closed_positions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE users (
                id UUID NOT NULL,
                email VARCHAR(255) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uq_users_email ON users (email)');
        $this->addSql("COMMENT ON COLUMN users.id IS '(DC2Type:user_id)'");

        $this->addSql(<<<'SQL'
            CREATE TABLE tax_position_ledgers (
                id SERIAL NOT NULL,
                user_id UUID NOT NULL,
                isin VARCHAR(12) NOT NULL,
                tax_category VARCHAR(20) NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uq_ledger_user_isin ON tax_position_ledgers (user_id, isin)');
        $this->addSql('CREATE INDEX idx_ledger_user_id ON tax_position_ledgers (user_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE open_positions (
                id SERIAL NOT NULL,
                ledger_id INTEGER NOT NULL,
                transaction_id UUID NOT NULL,
                date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                original_quantity NUMERIC(19,8) NOT NULL,
                remaining_quantity NUMERIC(19,8) NOT NULL,
                cost_per_unit_pln NUMERIC(19,8) NOT NULL,
                commission_per_unit_pln NUMERIC(19,8) NOT NULL,
                broker VARCHAR(50) NOT NULL,
                nbp_rate_currency VARCHAR(3) NOT NULL,
                nbp_rate_value NUMERIC(19,8) NOT NULL,
                nbp_rate_date DATE NOT NULL,
                nbp_rate_table VARCHAR(20) NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_open_positions_ledger FOREIGN KEY (ledger_id)
                    REFERENCES tax_position_ledgers (id) ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE INDEX idx_open_positions_ledger ON open_positions (ledger_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE closed_positions (
                id SERIAL NOT NULL,
                buy_transaction_id UUID NOT NULL,
                sell_transaction_id UUID NOT NULL,
                isin VARCHAR(12) NOT NULL,
                quantity NUMERIC(19,8) NOT NULL,
                cost_basis_pln NUMERIC(19,8) NOT NULL,
                proceeds_pln NUMERIC(19,8) NOT NULL,
                buy_commission_pln NUMERIC(19,8) NOT NULL,
                sell_commission_pln NUMERIC(19,8) NOT NULL,
                gain_loss_pln NUMERIC(19,8) NOT NULL,
                buy_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                sell_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                buy_nbp_rate_currency VARCHAR(3) NOT NULL,
                buy_nbp_rate_value NUMERIC(19,8) NOT NULL,
                buy_nbp_rate_date DATE NOT NULL,
                buy_nbp_rate_table VARCHAR(20) NOT NULL,
                sell_nbp_rate_currency VARCHAR(3) NOT NULL,
                sell_nbp_rate_value NUMERIC(19,8) NOT NULL,
                sell_nbp_rate_date DATE NOT NULL,
                sell_nbp_rate_table VARCHAR(20) NOT NULL,
                buy_broker VARCHAR(50) NOT NULL,
                sell_broker VARCHAR(50) NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_closed_sell_date ON closed_positions (sell_date)');
        $this->addSql('CREATE INDEX idx_closed_isin ON closed_positions (isin)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS closed_positions');
        $this->addSql('DROP TABLE IF EXISTS open_positions');
        $this->addSql('DROP TABLE IF EXISTS tax_position_ledgers');
        $this->addSql('DROP TABLE IF EXISTS users');
    }
}
