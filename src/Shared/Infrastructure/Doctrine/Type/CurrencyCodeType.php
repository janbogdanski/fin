<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Type;

use App\Shared\Domain\ValueObject\CurrencyCode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class CurrencyCodeType extends Type
{
    public const string NAME = 'currency_code';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(3)';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?CurrencyCode
    {
        if ($value === null) {
            return null;
        }

        return CurrencyCode::from((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof CurrencyCode) {
            throw new \InvalidArgumentException('Expected CurrencyCode instance');
        }

        return $value->value;
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
