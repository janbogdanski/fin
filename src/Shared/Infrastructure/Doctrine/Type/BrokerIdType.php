<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Type;

use App\Shared\Domain\ValueObject\BrokerId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class BrokerIdType extends Type
{
    public const string NAME = 'broker_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(50)';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?BrokerId
    {
        if ($value === null) {
            return null;
        }

        return BrokerId::of((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof BrokerId) {
            throw new \InvalidArgumentException('Expected BrokerId instance');
        }

        return $value->toString();
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
