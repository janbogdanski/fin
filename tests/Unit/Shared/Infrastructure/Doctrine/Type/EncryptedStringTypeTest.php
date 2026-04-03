<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Doctrine\Type;

use App\Shared\Infrastructure\Doctrine\Type\EncryptedStringType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;

final class EncryptedStringTypeTest extends TestCase
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

        // Use a stable test key (32 bytes, base64-encoded)
        EncryptedStringType::setEncryptionKey(base64_encode(str_repeat('k', 32)));
    }

    protected function tearDown(): void
    {
        EncryptedStringType::resetKey();
    }

    public function testEncryptAndDecryptRoundTrip(): void
    {
        $nip = '1234567890';

        $encrypted = $this->type->convertToDatabaseValue($nip, $this->platform);
        self::assertNotNull($encrypted);
        self::assertNotSame($nip, $encrypted, 'Encrypted value must differ from plaintext');

        $decrypted = $this->type->convertToPHPValue($encrypted, $this->platform);
        self::assertSame($nip, $decrypted);
    }

    public function testNullPassesThrough(): void
    {
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testEmptyStringPassesThrough(): void
    {
        self::assertNull($this->type->convertToDatabaseValue('', $this->platform));
        self::assertNull($this->type->convertToPHPValue('', $this->platform));
    }

    public function testDifferentEncryptionsProduceDifferentCiphertexts(): void
    {
        $nip = '1234567890';
        $encrypted1 = $this->type->convertToDatabaseValue($nip, $this->platform);
        $encrypted2 = $this->type->convertToDatabaseValue($nip, $this->platform);

        self::assertNotSame($encrypted1, $encrypted2, 'Each encryption must use a unique nonce');
    }

    public function testDecryptionFailsWithWrongKey(): void
    {
        $nip = '1234567890';
        $encrypted = $this->type->convertToDatabaseValue($nip, $this->platform);

        // Change the key
        EncryptedStringType::setEncryptionKey(base64_encode(str_repeat('x', 32)));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');
        $this->type->convertToPHPValue($encrypted, $this->platform);
    }

    public function testDecryptionFailsWithCorruptedData(): void
    {
        $this->expectException(\RuntimeException::class);
        // base64 of short garbage -- decodes but is invalid ciphertext
        $this->type->convertToPHPValue(base64_encode(str_repeat('x', 30)), $this->platform);
    }

    public function testMissingKeyThrows(): void
    {
        EncryptedStringType::resetKey();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ENCRYPTION_KEY not configured');
        $this->type->convertToDatabaseValue('test', $this->platform);
    }

    public function testTooShortKeyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('at least 16 characters');
        EncryptedStringType::setEncryptionKey('short');
    }

    public function testRawStringKeyWorks(): void
    {
        EncryptedStringType::setEncryptionKey('dev-only-key-replace-in-production-32b');

        $nip = '9876543210';
        $encrypted = $this->type->convertToDatabaseValue($nip, $this->platform);
        $decrypted = $this->type->convertToPHPValue($encrypted, $this->platform);

        self::assertSame($nip, $decrypted);
    }
}
