<?php

declare(strict_types=1);

namespace App\Tests\GoldenDataset\Concern;

/**
 * Shared XSD validation assertion for golden-dataset tests.
 *
 * Validates a PIT-38 XML string against the minimal structural schema at
 * tests/Fixtures/pit38_minimal.xsd using PHP's built-in DOMDocument.
 *
 * Usage: use this trait in any TestCase that calls PIT38XMLGenerator.
 */
trait AssertsPIT38XmlValid
{
    private function assertPIT38XmlValidatesAgainstSchema(string $xml): void
    {
        $xsdPath = __DIR__ . '/../../Fixtures/pit38_minimal.xsd';

        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        libxml_use_internal_errors(true);
        $isValid = $dom->schemaValidate($xsdPath);
        $errors  = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        if (! $isValid) {
            $messages = array_map(
                static fn (\LibXMLError $e): string => sprintf('[line %d] %s', $e->line, trim($e->message)),
                $errors,
            );
            static::fail(
                'Generated XML does not conform to pit38_minimal.xsd:' . PHP_EOL
                . implode(PHP_EOL, $messages),
            );
        }

        static::assertTrue(true, 'PIT-38 XML passed XSD schema validation');
    }
}
