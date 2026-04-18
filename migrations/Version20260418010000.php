<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Widens isin columns from VARCHAR(12) to VARCHAR(50) to support broker-specific
 * instrument symbols (e.g. XTB tickers like "AAPL.US") that do not follow the
 * ISO 6166 ISIN format and may exceed 12 characters.
 */
final class Version20260418010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Widen isin columns to VARCHAR(50) to accommodate non-ISIN broker symbols';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tax_position_ledgers ALTER COLUMN isin TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE closed_positions ALTER COLUMN isin TYPE VARCHAR(50)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tax_position_ledgers ALTER COLUMN isin TYPE VARCHAR(12)');
        $this->addSql('ALTER TABLE closed_positions ALTER COLUMN isin TYPE VARCHAR(12)');
    }
}
