<?php

declare(strict_types=1);

namespace App\BrokerImport\Infrastructure\Adapter\Spreadsheet;

final readonly class XlsxWorkbookReader
{
    private const string NS_MAIN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const string NS_OFFICE_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const string NS_PACKAGE_REL = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /**
     * @return array<string, list<list<string>>>
     */
    public function read(string $fileContent): array
    {
        if ($fileContent === '') {
            throw new \InvalidArgumentException('XLSX file is empty.');
        }

        if (! str_starts_with($fileContent, 'PK')) {
            throw new \InvalidArgumentException('File is not a valid XLSX workbook.');
        }

        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('PHP zip extension is required to parse XLSX files.');
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'taxpilot_xlsx_');

        if ($tmpFile === false) {
            throw new \RuntimeException('Failed to create a temporary XLSX file.');
        }

        if (file_put_contents($tmpFile, $fileContent) === false) {
            @unlink($tmpFile);

            throw new \RuntimeException('Failed to write the temporary XLSX file.');
        }

        $zip = new \ZipArchive();
        $openResult = $zip->open($tmpFile);

        if ($openResult !== true) {
            @unlink($tmpFile);

            throw new \InvalidArgumentException('Failed to open XLSX workbook.');
        }

        try {
            return $this->readWorkbook($zip);
        } finally {
            $zip->close();
            @unlink($tmpFile);
        }
    }

    /**
     * @return array<string, list<list<string>>>
     */
    private function readWorkbook(\ZipArchive $zip): array
    {
        $sharedStrings = $this->readSharedStrings($zip);
        $workbook = $this->loadXml($zip, 'xl/workbook.xml');
        $relationships = $this->loadXml($zip, 'xl/_rels/workbook.xml.rels');

        $relationshipMap = [];

        foreach ($this->xpath($relationships, '//pkg:Relationship') as $relationship) {
            $relationshipMap[(string) $relationship['Id']] = $this->normalizeSheetTarget((string) $relationship['Target']);
        }

        $sheets = [];

        foreach ($this->xpath($workbook, '//main:sheets/main:sheet') as $sheet) {
            $sheetName = (string) $sheet['name'];
            $relationshipId = (string) $sheet->attributes(self::NS_OFFICE_REL)['id'];
            $sheetPath = $relationshipMap[$relationshipId] ?? null;

            if ($sheetName === '' || $sheetPath === null) {
                continue;
            }

            $worksheet = $this->loadXml($zip, $sheetPath);
            $sheets[$sheetName] = $this->readRows($worksheet, $sharedStrings);
        }

        return $sheets;
    }

    /**
     * @return list<string>
     */
    private function readSharedStrings(\ZipArchive $zip): array
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }

        $sharedStrings = $this->loadXml($zip, 'xl/sharedStrings.xml');
        $values = [];

        foreach ($this->xpath($sharedStrings, '//main:si') as $item) {
            $parts = [];

            foreach ($this->xpath($item, './/main:t') as $textNode) {
                $parts[] = (string) $textNode;
            }

            $values[] = implode('', $parts);
        }

        return $values;
    }

    /**
     * @param list<string> $sharedStrings
     * @return list<list<string>>
     */
    private function readRows(\SimpleXMLElement $worksheet, array $sharedStrings): array
    {
        $rows = [];

        foreach ($this->xpath($worksheet, '//main:sheetData/main:row') as $row) {
            $values = [];

            foreach ($this->xpath($row, './main:c') as $cell) {
                $reference = (string) $cell['r'];
                $columnIndex = $this->extractColumnIndex($reference);

                while (count($values) < $columnIndex - 1) {
                    $values[] = '';
                }

                $values[] = $this->extractCellValue($cell, $sharedStrings);
            }

            $rows[] = $values;
        }

        return $rows;
    }

    /**
     * @param list<string> $sharedStrings
     */
    private function extractCellValue(\SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) $cell['t'];

        if ($type === 'inlineStr') {
            $parts = [];

            foreach ($this->xpath($cell, './main:is/main:t') as $textNode) {
                $parts[] = (string) $textNode;
            }

            return implode('', $parts);
        }

        $valueNode = $this->xpath($cell, './main:v')[0] ?? null;

        if ($valueNode === null) {
            return '';
        }

        $value = (string) $valueNode;

        if ($type === 's') {
            $index = (int) $value;

            return $sharedStrings[$index] ?? '';
        }

        return $value;
    }

    private function extractColumnIndex(string $reference): int
    {
        if (preg_match('/^([A-Z]+)/', $reference, $matches) !== 1) {
            return 1;
        }

        $index = 0;

        foreach (str_split($matches[1]) as $char) {
            $index = ($index * 26) + (ord($char) - 64);
        }

        return max($index, 1);
    }

    private function normalizeSheetTarget(string $target): string
    {
        $target = ltrim($target, '/');

        if (str_starts_with($target, 'xl/')) {
            return $target;
        }

        return 'xl/' . $target;
    }

    private function loadXml(\ZipArchive $zip, string $path): \SimpleXMLElement
    {
        $content = $zip->getFromName($path);

        if ($content === false) {
            throw new \InvalidArgumentException(sprintf('Missing XLSX entry: %s', $path));
        }

        $xml = simplexml_load_string($content, \SimpleXMLElement::class, LIBXML_NONET);

        if ($xml === false) {
            throw new \InvalidArgumentException(sprintf('Invalid XML in XLSX entry: %s', $path));
        }

        $xml->registerXPathNamespace('main', self::NS_MAIN);
        $xml->registerXPathNamespace('rel', self::NS_OFFICE_REL);
        $xml->registerXPathNamespace('pkg', self::NS_PACKAGE_REL);

        return $xml;
    }

    /**
     * @return list<\SimpleXMLElement>
     */
    private function xpath(\SimpleXMLElement $xml, string $expression): array
    {
        $xml->registerXPathNamespace('main', self::NS_MAIN);
        $xml->registerXPathNamespace('rel', self::NS_OFFICE_REL);
        $xml->registerXPathNamespace('pkg', self::NS_PACKAGE_REL);

        $result = $xml->xpath($expression);

        if (! is_array($result)) {
            return [];
        }

        /** @var list<\SimpleXMLElement> $normalized */
        $normalized = array_values($result);

        return $normalized;
    }
}
