<?php

declare(strict_types=1);

namespace App\BrokerImport\Application\Port;

use App\Shared\Domain\ValueObject\UserId;

interface BrokerAdapterRequestPort
{
    public function submit(UserId $userId, string $filename, string $fileContent): void;

    public function deleteByUser(UserId $userId): void;
}
