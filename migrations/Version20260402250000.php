<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fix nip column length to accommodate encrypted_string type.
 *
 * The original migration created nip as VARCHAR(10) matching the raw NIP digit count.
 * The Doctrine mapping uses encrypted_string type (AES-256-GCM + base64), which produces
 * values ~60 chars long — well within VARCHAR(255) but exceeding VARCHAR(10).
 */
final class Version20260402250000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Resize users.nip to VARCHAR(255) to fit encrypted_string output';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ALTER COLUMN nip TYPE VARCHAR(255)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ALTER COLUMN nip TYPE VARCHAR(10)');
    }
}
