<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Type;

use Brick\Math\BigDecimal;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Maps Brick\Math\BigDecimal to NUMERIC(19,8) in PostgreSQL.
 * Scale 8 preserves intermediate calculation precision.
 */
final class MoneyAmountType extends Type
{
    public const string NAME = 'money_amount';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'NUMERIC(19,8)';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?BigDecimal
    {
        if ($value === null) {
            return null;
        }

        return BigDecimal::of((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof BigDecimal) {
            throw new \InvalidArgumentException('Expected BigDecimal instance');
        }

        return (string) $value;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
