<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * P2-116: Widen first_name and last_name columns to VARCHAR(255) to accommodate
 * AES-256-GCM encrypted values stored via EncryptedStringType Doctrine type.
 *
 * Encryption is applied transparently at the Doctrine mapping level (encrypted_string type).
 * A 50-char plaintext name produces ~104 chars of base64-encoded ciphertext
 * (12-byte nonce + 50-byte ciphertext + 16-byte tag = 78 bytes raw → 104 chars base64).
 * VARCHAR(100) is insufficient; VARCHAR(255) allows up to ~163 chars of plaintext.
 *
 * -- Encryption applied via Doctrine type (AES-256-GCM). Existing NULL rows remain NULL.
 */
final class Version20260405020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Widen first_name and last_name to VARCHAR(255) for AES-256-GCM encrypted storage (P2-116)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ALTER COLUMN first_name TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE users ALTER COLUMN last_name TYPE VARCHAR(255)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ALTER COLUMN last_name TYPE VARCHAR(100)');
        $this->addSql('ALTER TABLE users ALTER COLUMN first_name TYPE VARCHAR(100)');
    }
}
