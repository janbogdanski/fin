<?php

declare(strict_types=1);

namespace App\Tests\GoldenDataset\Concern;

/**
 * Validates generated PIT-38 XML against the official Ministry of Finance XSD.
 *
 * The official XSD and all its dependencies are bundled locally at
 * tests/Fixtures/official_xsd/schemat.xsd (downloaded from
 * http://crd.gov.pl/wzor/2025/10/09/13914/schemat.xsd).
 *
 * Use this trait in tests that validate XML intended for actual submission
 * (with complete personal data, kodUrzedu, adresZamieszkania, dateOfBirth).
 */
trait AssertsOfficialPIT38XmlValid
{
    private function assertPIT38XmlValidatesAgainstOfficialSchema(string $xml): void
    {
        $xsdPath = __DIR__ . '/../../Fixtures/official_xsd/schemat.xsd';

        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        libxml_use_internal_errors(true);
        $isValid = $dom->schemaValidate($xsdPath);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        if (! $isValid) {
            $messages = array_map(
                static fn (\LibXMLError $e): string => sprintf('[line %d] %s', $e->line, trim($e->message)),
                $errors,
            );
            static::fail(
                'Generated XML does not conform to official MF XSD (schemat.xsd):' . PHP_EOL
                . implode(PHP_EOL, $messages),
            );
        }

        static::assertTrue(true, 'PIT-38 XML passed official MF XSD validation');
    }
}
