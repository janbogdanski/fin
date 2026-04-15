<?php

declare(strict_types=1);

namespace App\Tests\Unit\BrokerImport\Infrastructure;

use App\BrokerImport\Application\DTO\FileValidationError;
use App\BrokerImport\Infrastructure\Validation\UploadedFileValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class UploadedFileValidatorTest extends TestCase
{
    private UploadedFileValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new UploadedFileValidator();
    }

    public function testNullFileReturnsNoFileError(): void
    {
        self::assertSame(FileValidationError::NO_FILE, $this->validator->validate(null));
    }

    public function testInvalidUploadReturnsNoFileError(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('isValid')->willReturn(false);

        self::assertSame(FileValidationError::NO_FILE, $this->validator->validate($file));
    }

    public function testOversizedFileReturnsTooLargeError(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('isValid')->willReturn(true);
        $file->method('getSize')->willReturn(11 * 1024 * 1024); // 11 MB

        self::assertSame(FileValidationError::TOO_LARGE, $this->validator->validate($file));
    }

    public function testUnsupportedExtensionReturnsInvalidExtensionError(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('isValid')->willReturn(true);
        $file->method('getSize')->willReturn(100);
        $file->method('getClientOriginalName')->willReturn('data.pdf');

        self::assertSame(FileValidationError::INVALID_EXTENSION, $this->validator->validate($file));
    }

    public function testInvalidMimeTypeReturnsError(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('isValid')->willReturn(true);
        $file->method('getSize')->willReturn(100);
        $file->method('getClientOriginalName')->willReturn('data.csv');
        $file->method('getMimeType')->willReturn('application/pdf');

        self::assertSame(FileValidationError::INVALID_MIME_TYPE, $this->validator->validate($file));
    }

    public function testValidCsvFileReturnsNull(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('isValid')->willReturn(true);
        $file->method('getSize')->willReturn(100);
        $file->method('getClientOriginalName')->willReturn('transactions.csv');
        $file->method('getMimeType')->willReturn('text/csv');

        self::assertNull($this->validator->validate($file));
    }

    public function testValidCsvFileWithTextPlainMimeReturnsNull(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('isValid')->willReturn(true);
        $file->method('getSize')->willReturn(100);
        $file->method('getClientOriginalName')->willReturn('export.csv');
        $file->method('getMimeType')->willReturn('text/plain');

        self::assertNull($this->validator->validate($file));
    }

    public function testValidXlsxFileReturnsNull(): void
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'xlsx_test_');
        assert($tmpPath !== false);
        // Write ZIP/XLSX magic bytes: PK\x03\x04
        file_put_contents($tmpPath, "\x50\x4B\x03\x04" . str_repeat('x', 100));

        $file = $this->createMock(UploadedFile::class);
        $file->method('isValid')->willReturn(true);
        $file->method('getSize')->willReturn(100);
        $file->method('getClientOriginalName')->willReturn('statement.xlsx');
        $file->method('getMimeType')->willReturn('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $file->method('getPathname')->willReturn($tmpPath);

        $result = $this->validator->validate($file);

        unlink($tmpPath);

        self::assertNull($result);
    }

    public function testXlsxWithInvalidMagicBytesReturnsInvalidMimeType(): void
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'xlsx_test_');
        assert($tmpPath !== false);
        // Write plain text content instead of valid ZIP/XLSX bytes
        file_put_contents($tmpPath, "Date,ISIN,Quantity\n");

        $file = $this->createMock(UploadedFile::class);
        $file->method('isValid')->willReturn(true);
        $file->method('getSize')->willReturn(100);
        $file->method('getClientOriginalName')->willReturn('malicious.xlsx');
        $file->method('getMimeType')->willReturn('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $file->method('getPathname')->willReturn($tmpPath);

        $result = $this->validator->validate($file);

        unlink($tmpPath);

        self::assertSame(FileValidationError::INVALID_MIME_TYPE, $result);
    }

    public function testLegacyXlsFileReturnsInvalidExtension(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('isValid')->willReturn(true);
        $file->method('getSize')->willReturn(100);
        $file->method('getClientOriginalName')->willReturn('statement.xls');
        $file->method('getMimeType')->willReturn('application/vnd.ms-excel');

        self::assertSame(FileValidationError::INVALID_EXTENSION, $this->validator->validate($file));
    }

    public function testReadContentReturnsUnreadableForEmptyFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        assert($tmpFile !== false);
        file_put_contents($tmpFile, '');

        try {
            $file = $this->createMock(UploadedFile::class);
            $file->method('getPathname')->willReturn($tmpFile);

            self::assertSame(FileValidationError::UNREADABLE, $this->validator->readContent($file));
        } finally {
            unlink($tmpFile);
        }
    }

    public function testReadContentReturnsStringForValidFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        assert($tmpFile !== false);
        file_put_contents($tmpFile, 'header1,header2\nval1,val2');

        try {
            $file = $this->createMock(UploadedFile::class);
            $file->method('getPathname')->willReturn($tmpFile);

            $result = $this->validator->readContent($file);
            self::assertIsString($result);
            self::assertStringContainsString('header1', $result);
        } finally {
            unlink($tmpFile);
        }
    }
}
