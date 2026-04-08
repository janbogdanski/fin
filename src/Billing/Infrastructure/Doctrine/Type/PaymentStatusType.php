<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Doctrine\Type;

use App\Billing\Domain\ValueObject\PaymentStatus;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class PaymentStatusType extends Type
{
    public const string NAME = 'payment_status';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL([
            'length' => 20,
        ]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?PaymentStatus
    {
        if ($value === null) {
            return null;
        }

        return PaymentStatus::from((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof PaymentStatus) {
            return $value->value;
        }

        return (string) $value;
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
