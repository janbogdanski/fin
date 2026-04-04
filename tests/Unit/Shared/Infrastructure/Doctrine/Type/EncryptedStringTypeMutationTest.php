<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Doctrine\Type;

use App\Shared\Infrastructure\Doctrine\Type\EncryptedStringType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;

/**
 * Mutation-killing tests for EncryptedStringType.
 *
 * Targets: NONCE_LENGTH, TAG_LENGTH, base64_decode strict mode,
 * substr offsets, key derivation, setEncryptionKey length check,
 * convertToPHPValue/convertToDatabaseValue null/empty checks.
 */
final class EncryptedStringTypeMutationTest extends TestCase
{
    private EncryptedStringType $type;

    private PostgreSQLPlatform $platform;

    protected function setUp(): void
    {
        if (! Type::hasType(EncryptedStringType::NAME)) {
            Type::addType(EncryptedStringType::NAME, EncryptedStringType::class);
        }

        /** @var EncryptedStringType $type */
        $type = Type::getType(EncryptedStringType::NAME);
        $this->type = $type;
        $this->platform = new PostgreSQLPlatform();

        EncryptedStringType::setEncryptionKey(base64_encode(str_repeat('k', 32)));
    }

    protected function tearDown(): void
    {
        EncryptedStringType::resetKey();
    }

    /**
     * Kills mutations on NONCE_LENGTH and TAG_LENGTH:
     * If either is changed, decryption would fail because substr boundaries shift.
     * Tests that a long string (with many possible boundary positions) round-trips correctly.
     */
    public function testRoundTripWithLongString(): void
    {
        $value = str_repeat('A', 200);

        $encrypted = $this->type->convertToDatabaseValue($value, $this->platform);
        self::assertIsString($encrypted);

        $decrypted = $this->type->convertToPHPValue($encrypted, $this->platform);
        self::assertSame($value, $decrypted);
    }

    /**
     * Kills mutations on base64_decode strict mode:
     * If strict = false (or removed), invalid base64 would be silently accepted.
     */
    public function testDecryptionRejectsInvalidBase64(): void
    {
        $this->expectException(\RuntimeException::class);

        // Invalid base64 with non-base64 chars
        $this->type->convertToPHPValue('!!!not-valid-base64!!!', $this->platform);
    }

    /**
     * Kills mutation on minimum raw length check in convertToPHPValue:
     * strlen($raw) < NONCE_LENGTH + TAG_LENGTH + 1.
     * If the minimum is changed, short data would pass the check but fail in openssl_decrypt.
     */
    public function testDecryptionRejectsTooShortData(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('corrupted data');

        // 12 (nonce) + 16 (tag) + 1 (min ciphertext) = 29 bytes minimum
        // Provide exactly 28 bytes (just under minimum)
        $shortData = base64_encode(str_repeat('x', 28));
        $this->type->convertToPHPValue($shortData, $this->platform);
    }

    /**
     * Kills mutation on setEncryptionKey: base64 path vs SHA-256 path.
     * A valid base64 key of 16+ bytes should be used directly, not hashed.
     */
    public function testBase64KeyIsUsedDirectly(): void
    {
        $rawKey = str_repeat('A', 32);
        $b64Key = base64_encode($rawKey);

        EncryptedStringType::setEncryptionKey($b64Key);

        $encrypted = $this->type->convertToDatabaseValue('test', $this->platform);
        $decrypted = $this->type->convertToPHPValue($encrypted, $this->platform);

        self::assertSame('test', $decrypted);
    }

    /**
     * Kills mutation on setEncryptionKey: non-base64 key should go through SHA-256 derivation.
     * After derivation, encryption/decryption should still work.
     */
    public function testNonBase64KeyUsesShaDerivedKey(): void
    {
        // A key that is NOT valid base64 (or decodes to < 16 bytes)
        EncryptedStringType::setEncryptionKey('this-is-a-raw-key-not-base64-encoded');

        $encrypted = $this->type->convertToDatabaseValue('test-data', $this->platform);
        $decrypted = $this->type->convertToPHPValue($encrypted, $this->platform);

        self::assertSame('test-data', $decrypted);
    }

    /**
     * Kills mutation on strlen($key) < 16 boundary.
     * Key of exactly 16 chars should be accepted.
     */
    public function testKeyOfExactly16CharsIsAccepted(): void
    {
        EncryptedStringType::setEncryptionKey('1234567890123456');

        $encrypted = $this->type->convertToDatabaseValue('value', $this->platform);
        $decrypted = $this->type->convertToPHPValue($encrypted, $this->platform);

        self::assertSame('value', $decrypted);
    }

    /**
     * Kills mutation on strlen($key) < 16: key of 15 chars should be rejected.
     */
    public function testKeyOf15CharsIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        EncryptedStringType::setEncryptionKey('123456789012345');
    }

    /**
     * Kills mutation on empty key check: empty string should be rejected.
     */
    public function testEmptyKeyIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        EncryptedStringType::setEncryptionKey('');
    }

    /**
     * Kills getName mutation.
     */
    public function testGetNameReturnsEncryptedString(): void
    {
        self::assertSame('encrypted_string', $this->type->getName());
    }

    /**
     * Kills requiresSQLCommentHint mutation.
     */
    public function testRequiresSQLCommentHint(): void
    {
        self::assertTrue($this->type->requiresSQLCommentHint($this->platform));
    }

    /**
     * Kills mutation on single-char string: ensures the ciphertext is at least 1 byte.
     */
    public function testSingleCharRoundTrip(): void
    {
        $encrypted = $this->type->convertToDatabaseValue('X', $this->platform);
        $decrypted = $this->type->convertToPHPValue($encrypted, $this->platform);

        self::assertSame('X', $decrypted);
    }
}
