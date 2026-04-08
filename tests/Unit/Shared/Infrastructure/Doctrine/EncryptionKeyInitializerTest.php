<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Doctrine;

use App\Shared\Infrastructure\Doctrine\EncryptionKeyInitializer;
use App\Shared\Infrastructure\Doctrine\Type\EncryptedStringType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

final class EncryptionKeyInitializerTest extends TestCase
{
    private const string TEST_KEY = 'test-encryption-key-minimum-length-ok';

    protected function tearDown(): void
    {
        EncryptedStringType::resetKey();
    }

    public function testMainRequestSetsEncryptionKey(): void
    {
        $initializer = new EncryptionKeyInitializer(self::TEST_KEY);
        $event = $this->createRequestEvent(isMainRequest: true);

        $initializer($event);

        // Key was set — verify by checking that convertToDatabaseValue works without throwing
        $type = new EncryptedStringType();
        $platform = $this->createMock(\Doctrine\DBAL\Platforms\AbstractPlatform::class);
        $encrypted = $type->convertToDatabaseValue('test-value', $platform);

        self::assertIsString($encrypted);
        self::assertNotEmpty($encrypted);
        self::assertNotSame('test-value', $encrypted);
    }

    public function testSubRequestDoesNotOverrideExistingKey(): void
    {
        // Arrange: key is established by a main request
        (new EncryptionKeyInitializer(self::TEST_KEY))($this->createRequestEvent(isMainRequest: true));

        // Act: sub-request fires (should be a complete no-op — must not clear or replace the key)
        (new EncryptionKeyInitializer(self::TEST_KEY))($this->createRequestEvent(isMainRequest: false));

        // Assert: key is still set and functional after the sub-request
        $type = new EncryptedStringType();
        $platform = $this->createMock(\Doctrine\DBAL\Platforms\AbstractPlatform::class);
        $encrypted = $type->convertToDatabaseValue('test-value', $platform);

        self::assertIsString($encrypted);
        self::assertNotEmpty($encrypted);
        self::assertNotSame('test-value', $encrypted);
    }

    private function createRequestEvent(bool $isMainRequest): RequestEvent
    {
        $kernel = $this->createMock(KernelInterface::class);
        $request = Request::create('/');
        $requestType = $isMainRequest
            ? HttpKernelInterface::MAIN_REQUEST
            : HttpKernelInterface::SUB_REQUEST;

        return new RequestEvent($kernel, $request, $requestType);
    }
}
