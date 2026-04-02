<?php

declare(strict_types=1);

namespace App\Tests\Unit\Billing\Domain;

use App\Billing\Domain\ValueObject\ProductCode;
use PHPUnit\Framework\TestCase;

final class ProductCodeTest extends TestCase
{
    public function testProCoversStandard(): void
    {
        self::assertTrue(ProductCode::PRO->coversAtLeast(ProductCode::STANDARD));
    }

    public function testProCoversPro(): void
    {
        self::assertTrue(ProductCode::PRO->coversAtLeast(ProductCode::PRO));
    }

    public function testStandardCoversStandard(): void
    {
        self::assertTrue(ProductCode::STANDARD->coversAtLeast(ProductCode::STANDARD));
    }

    public function testStandardDoesNotCoverPro(): void
    {
        self::assertFalse(ProductCode::STANDARD->coversAtLeast(ProductCode::PRO));
    }
}
