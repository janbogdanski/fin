<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sprint 8 review fix: UNIQUE constraint on prior_year_losses (user_id, loss_year, tax_category).
 * Prevents duplicate entries for the same user/year/category combination.
 */
final class Version20260402230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add UNIQUE constraint on prior_year_losses (user_id, loss_year, tax_category)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX uniq_prior_year_loss_user_year_cat ON prior_year_losses (user_id, loss_year, tax_category)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_prior_year_loss_user_year_cat');
    }
}
