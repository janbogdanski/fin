<?php

declare(strict_types=1);

namespace App\BrokerImport\Domain\Exception;

final class UnsupportedBrokerFormatException extends \RuntimeException
{
    public function __construct(string $filename)
    {
        parent::__construct(
            sprintf('No broker adapter supports the uploaded file: "%s"', $filename),
        );
    }
}
