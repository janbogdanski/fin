<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Full Doctrine persistence: imported_transactions + closed_positions user/category columns.
 */
final class Version20260402190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create imported_transactions table, add user_id and tax_category to closed_positions';
    }

    public function up(Schema $schema): void
    {
        // imported_transactions: stores all CSV-imported transactions per user
        $this->addSql(<<<'SQL'
            CREATE TABLE imported_transactions (
                id UUID NOT NULL,
                user_id UUID NOT NULL,
                import_batch_id UUID NOT NULL,
                broker_id VARCHAR(50) NOT NULL,
                isin VARCHAR(12) DEFAULT NULL,
                symbol VARCHAR(50) NOT NULL,
                transaction_type VARCHAR(30) NOT NULL,
                transaction_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                quantity NUMERIC(19,8) NOT NULL,
                price_amount NUMERIC(19,8) NOT NULL,
                price_currency VARCHAR(3) NOT NULL,
                commission_amount NUMERIC(19,8) NOT NULL,
                commission_currency VARCHAR(3) NOT NULL,
                description TEXT NOT NULL DEFAULT '',
                content_hash VARCHAR(64) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_imported_tx_user_id ON imported_transactions (user_id)');
        $this->addSql('CREATE INDEX idx_imported_tx_user_batch ON imported_transactions (user_id, import_batch_id)');
        $this->addSql('CREATE INDEX idx_imported_tx_user_type ON imported_transactions (user_id, transaction_type)');
        $this->addSql('CREATE INDEX idx_imported_tx_content_hash ON imported_transactions (user_id, content_hash)');

        // closed_positions: add user_id and tax_category for query ports
        $this->addSql('ALTER TABLE closed_positions ADD COLUMN user_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE closed_positions ADD COLUMN tax_category VARCHAR(20) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_closed_user_year_cat ON closed_positions (user_id, sell_date, tax_category)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_closed_user_year_cat');
        $this->addSql('ALTER TABLE closed_positions DROP COLUMN IF EXISTS tax_category');
        $this->addSql('ALTER TABLE closed_positions DROP COLUMN IF EXISTS user_id');
        $this->addSql('DROP TABLE IF EXISTS imported_transactions');
    }
}
