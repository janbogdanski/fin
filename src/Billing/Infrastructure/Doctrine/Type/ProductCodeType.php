<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Doctrine\Type;

use App\Billing\Domain\ValueObject\ProductCode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class ProductCodeType extends Type
{
    public const string NAME = 'product_code';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 20]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ProductCode
    {
        if ($value === null) {
            return null;
        }

        return ProductCode::from((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof ProductCode) {
            return $value->value;
        }

        return (string) $value;
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
