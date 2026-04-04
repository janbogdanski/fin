<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add used_in_years column to prior_year_losses table.
 *
 * Stores a JSON array of tax years in which this loss entry was applied as a deduction.
 * A non-empty array means the entry is locked: delete and originalAmount reduction are blocked.
 *
 * @see P0-010 — PriorYearLoss mutable after use
 * @see art. 9 ust. 3 ustawy o PIT
 */
final class Version20260404000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add used_in_years JSON column to prior_year_losses for mutation lock guard';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE prior_year_losses ADD COLUMN used_in_years TEXT NOT NULL DEFAULT '[]'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE prior_year_losses DROP COLUMN used_in_years');
    }
}
