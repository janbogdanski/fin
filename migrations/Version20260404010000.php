<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * P2-105: Add FK constraints (ON DELETE RESTRICT) from user_id columns to users(id).
 *
 * Tables affected:
 *   - closed_positions   (user_id added in Version20260402190000)
 *   - imported_transactions (user_id added in Version20260402190000)
 *   - prior_year_losses  (user_id added in Version20260402210000)
 *   - dividend_tax_results (user_id added in Version20260402220000)
 *
 * ON DELETE RESTRICT is intentional: these tables hold audit/financial data that must
 * NOT be silently wiped when a user account is removed. Any user deletion flow must
 * explicitly resolve or archive these rows first.
 */
final class Version20260404010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ON DELETE RESTRICT FK constraints: closed_positions, imported_transactions, prior_year_losses, dividend_tax_results → users(id)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE closed_positions
                ADD CONSTRAINT fk_closed_positions_user_id
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE imported_transactions
                ADD CONSTRAINT fk_imported_transactions_user_id
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE prior_year_losses
                ADD CONSTRAINT fk_prior_year_losses_user_id
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE dividend_tax_results
                ADD CONSTRAINT fk_dividend_tax_results_user_id
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dividend_tax_results DROP CONSTRAINT IF EXISTS fk_dividend_tax_results_user_id');
        $this->addSql('ALTER TABLE prior_year_losses DROP CONSTRAINT IF EXISTS fk_prior_year_losses_user_id');
        $this->addSql('ALTER TABLE imported_transactions DROP CONSTRAINT IF EXISTS fk_imported_transactions_user_id');
        $this->addSql('ALTER TABLE closed_positions DROP CONSTRAINT IF EXISTS fk_closed_positions_user_id');
    }
}
