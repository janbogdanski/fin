<?php

declare(strict_types=1);

namespace App\BrokerImport\Infrastructure\Validation;

use App\BrokerImport\Application\DTO\FileValidationError;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Validates uploaded broker files: size, extension, MIME type, readability.
 *
 * Returns null when valid, or a FileValidationError describing the failure.
 * Content-level validation (hash, broker detection) is NOT this class's responsibility.
 */
final readonly class UploadedFileValidator
{
    /**
     * Pragmatic limit: real broker exports are typically 1-5 MB.
     */
    private const int MAX_FILE_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB

    private const array ALLOWED_MIME_TYPES = [
        'text/csv',
        'text/plain',
        'application/csv',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    private const array ALLOWED_EXTENSIONS = [
        'csv',
        'xlsx',
    ];

    public function validate(?UploadedFile $file): ?FileValidationError
    {
        if ($file === null || ! $file->isValid()) {
            return FileValidationError::NO_FILE;
        }

        if ($file->getSize() > self::MAX_FILE_SIZE_BYTES) {
            return FileValidationError::TOO_LARGE;
        }

        $extension = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return FileValidationError::INVALID_EXTENSION;
        }

        if (! in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            return FileValidationError::INVALID_MIME_TYPE;
        }

        return null;
    }

    /**
     * Read and validate file content. Returns content string or FileValidationError.
     *
     * Checks both readability and content-size (defense-in-depth against
     * race conditions where file grows between stat and read).
     */
    public function readContent(UploadedFile $file): string|FileValidationError
    {
        $content = file_get_contents($file->getPathname());

        if ($content === false || $content === '') {
            return FileValidationError::UNREADABLE;
        }

        if (strlen($content) > self::MAX_FILE_SIZE_BYTES) {
            return FileValidationError::TOO_LARGE;
        }

        return $content;
    }
}
