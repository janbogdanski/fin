<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * US-S8-05: Prior Year Loss persistence table.
 */
final class Version20260402210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create prior_year_losses table for loss carryforward tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE prior_year_losses (
                id UUID NOT NULL,
                user_id UUID NOT NULL,
                loss_year INT NOT NULL,
                tax_category VARCHAR(20) NOT NULL,
                original_amount NUMERIC(19,2) NOT NULL,
                remaining_amount NUMERIC(19,2) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_prior_loss_user_year ON prior_year_losses (user_id, loss_year)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS prior_year_losses');
    }
}
