<?php

declare(strict_types=1);

namespace App\BrokerImport\Application\DTO;

enum FileValidationError: string
{
    case NO_FILE = 'Nie przeslano poprawnego pliku.';
    case TOO_LARGE = 'Plik jest zbyt duzy. Maksymalny rozmiar to 10 MB.';
    case INVALID_EXTENSION = 'Nieprawidlowe rozszerzenie pliku. Dozwolone: .csv, .xlsx';
    case INVALID_MIME_TYPE = 'Nieprawidlowy format pliku. Dozwolone: CSV oraz XLSX.';
    case UNREADABLE = 'Nie mozna odczytac zawartosci pliku.';
}
