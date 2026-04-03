<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine DBAL type that encrypts/decrypts string values at rest.
 *
 * Uses AES-256-GCM (authenticated encryption) with ENCRYPTION_KEY from env.
 * Stored format: base64(nonce + ciphertext + tag).
 */
final class EncryptedStringType extends Type
{
    public const string NAME = 'encrypted_string';

    private const string CIPHER = 'aes-256-gcm';

    private const int NONCE_LENGTH = 12;

    private const int TAG_LENGTH = 16;

    private static ?string $encryptionKey = null;

    /**
     * Accepts a key as either base64-encoded or raw string.
     * If base64-decoded result is >= 16 bytes, uses it as-is.
     * Otherwise, derives a 32-byte key via SHA-256 hash of the raw input.
     * Minimum raw key length: 16 characters.
     */
    public static function setEncryptionKey(string $key): void
    {
        if ($key === '' || strlen($key) < 16) {
            throw new \InvalidArgumentException('ENCRYPTION_KEY must be at least 16 characters.');
        }

        $decoded = base64_decode($key, true);

        if ($decoded !== false && strlen($decoded) >= 16) {
            self::$encryptionKey = $decoded;

            return;
        }

        // Derive a 32-byte key from raw string via SHA-256
        self::$encryptionKey = hash('sha256', $key, true);
    }

    public static function resetKey(): void
    {
        self::$encryptionKey = null;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $column['length'] = 255;

        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $key = $this->getKey();
        $raw = base64_decode((string) $value, true);

        if ($raw === false || strlen($raw) < self::NONCE_LENGTH + self::TAG_LENGTH + 1) {
            throw new \RuntimeException('Invalid encrypted value: corrupted data.');
        }

        $nonce = substr($raw, 0, self::NONCE_LENGTH);
        $tag = substr($raw, -self::TAG_LENGTH);
        $ciphertext = substr($raw, self::NONCE_LENGTH, -self::TAG_LENGTH);

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag);

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed: invalid key or corrupted data.');
        }

        return $plaintext;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $key = $this->getKey();
        $nonce = random_bytes(self::NONCE_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            (string) $value,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            self::TAG_LENGTH,
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed.');
        }

        return base64_encode($nonce . $ciphertext . $tag);
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }

    private function getKey(): string
    {
        if (self::$encryptionKey === null) {
            throw new \RuntimeException('ENCRYPTION_KEY not configured. Call EncryptedStringType::setEncryptionKey() before using encrypted fields.');
        }

        return self::$encryptionKey;
    }
}
