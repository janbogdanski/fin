<?php

declare(strict_types=1);

namespace App\BrokerImport\Domain\Exception;

/**
 * Thrown when the user selects a specific broker in the wizard,
 * but the uploaded file does not match that broker's format.
 */
final class BrokerFileMismatchException extends \RuntimeException
{
    public function __construct(
        public readonly string $selectedBrokerId,
        string $filename,
    ) {
        parent::__construct(
            sprintf(
                'The uploaded file "%s" does not match the selected broker "%s". Please verify you chose the correct broker.',
                $filename,
                $selectedBrokerId,
            ),
        );
    }
}
