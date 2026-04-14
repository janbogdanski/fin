<?php

declare(strict_types=1);

namespace App\Tests\Unit\Declaration;

use App\Declaration\Domain\DTO\PIT38Data;
use App\Declaration\Domain\DTO\PolishAddress;
use App\Declaration\Domain\Service\PIT38XMLGenerator;
use App\Tests\GoldenDataset\Concern\AssertsOfficialPIT38XmlValid;
use PHPUnit\Framework\TestCase;

/**
 * Validates generated PIT-38 XML against the official Ministry of Finance XSD.
 *
 * Covers BETA-BLK-005: CI must validate XML against the official MF XSD, not a hand-crafted minimal one.
 *
 * Each test provides a complete set of data required by the official XSD:
 * - NIP + ImiePierwsze + Nazwisko + DataUrodzenia (TIdentyfikatorOsobyFizycznej1)
 * - AdresZamieszkania (TOsobaFizyczna3 requires it)
 * - KodUrzedu (TNaglowek requires it, type TKodUS1 — must be a valid 4-char code)
 * - taxYear >= 2025 (XSD restriction on Rok element)
 */
final class PIT38OfficialXsdValidationTest extends TestCase
{
    use AssertsOfficialPIT38XmlValid;

    private PIT38XMLGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new PIT38XMLGenerator();
    }

    /**
     * Equity-only gain scenario — all mandatory PozycjeSzczegolowe fields present.
     * P_26/P_27/P_28/P_31/P_32/P_33 always emitted. P_51 always emitted.
     * No crypto section (P_41/P_42/P_43 absent).
     */
    public function testEquityOnlyGainValidatesAgainstOfficialXsd(): void
    {
        $data = new PIT38Data(
            taxYear: 2025,
            nip: '5260000005',
            firstName: 'Anna',
            lastName: 'Nowak',
            equityProceeds: '79000.00',
            equityCosts: '68858.00',
            equityIncome: '10142.00',
            equityLoss: '0',
            equityTaxBase: '10142',
            equityTax: '1927',
            dividendGross: '0',
            dividendWHT: '0',
            dividendTaxDue: '0',
            cryptoProceeds: '0',
            cryptoCosts: '0',
            cryptoIncome: '0',
            cryptoLoss: '0',
            cryptoTax: '0',
            totalTax: '1927',
            isCorrection: false,
            kodUrzedu: '0202',
            adresZamieszkania: new PolishAddress(
                miejscowosc: 'Warszawa',
                nrDomu: '1',
                kodPocztowy: '00-001',
                wojewodztwo: 'mazowieckie',
                powiat: 'Warszawa',
                gmina: 'Warszawa',
            ),
            dateOfBirth: '1985-03-15',
        );

        $xml = $this->generator->generate($data);

        $this->assertPIT38XmlValidatesAgainstOfficialSchema($xml);
    }

    /**
     * Equity loss scenario — P_29 (strata) instead of P_28 (dochod).
     * P_31=0, P_32=19, P_33=0, P_51=0 still required.
     */
    public function testEquityLossValidatesAgainstOfficialXsd(): void
    {
        $data = new PIT38Data(
            taxYear: 2025,
            nip: '5260000005',
            firstName: 'Jan',
            lastName: 'Kowalski',
            equityProceeds: '50000.00',
            equityCosts: '75000.00',
            equityIncome: '0',
            equityLoss: '25000.00',
            equityTaxBase: '0',
            equityTax: '0',
            dividendGross: '0',
            dividendWHT: '0',
            dividendTaxDue: '0',
            cryptoProceeds: '0',
            cryptoCosts: '0',
            cryptoIncome: '0',
            cryptoLoss: '0',
            cryptoTax: '0',
            totalTax: '0',
            isCorrection: false,
            kodUrzedu: '0202',
            adresZamieszkania: new PolishAddress(
                miejscowosc: 'Krakow',
                nrDomu: '5A',
                kodPocztowy: '30-001',
                wojewodztwo: 'malopolskie',
                powiat: 'Krakow',
                gmina: 'Krakow',
            ),
            dateOfBirth: '1990-07-22',
        );

        $xml = $this->generator->generate($data);

        $this->assertPIT38XmlValidatesAgainstOfficialSchema($xml);
    }

    /**
     * Crypto-only scenario — P_41/P_42/P_43 group emitted.
     * Equity section: P_26='0.00', P_27='0.00', P_28='0.00', P_31=0, P_32=19, P_33=0.
     */
    public function testCryptoOnlyValidatesAgainstOfficialXsd(): void
    {
        $data = new PIT38Data(
            taxYear: 2025,
            nip: '5260000005',
            firstName: 'Piotr',
            lastName: 'Wisniewski',
            equityProceeds: '0',
            equityCosts: '0',
            equityIncome: '0',
            equityLoss: '0',
            equityTaxBase: '0',
            equityTax: '0',
            dividendGross: '0',
            dividendWHT: '0',
            dividendTaxDue: '0',
            cryptoProceeds: '18000.00',
            cryptoCosts: '12000.00',
            cryptoIncome: '6000.00',
            cryptoLoss: '0',
            cryptoTax: '1140',
            totalTax: '1140',
            isCorrection: false,
            kodUrzedu: '0202',
            adresZamieszkania: new PolishAddress(
                miejscowosc: 'Gdansk',
                nrDomu: '10',
                kodPocztowy: '80-001',
                wojewodztwo: 'pomorskie',
                powiat: 'Gdansk',
                gmina: 'Gdansk',
            ),
            dateOfBirth: '1988-11-03',
        );

        $xml = $this->generator->generate($data);

        $this->assertPIT38XmlValidatesAgainstOfficialSchema($xml);
    }

    /**
     * Full scenario: equity gain + crypto + foreign dividends.
     */
    public function testFullScenarioValidatesAgainstOfficialXsd(): void
    {
        $data = new PIT38Data(
            taxYear: 2025,
            nip: '5261040828',
            firstName: 'Marek',
            lastName: 'Zielinski',
            equityProceeds: '120000.00',
            equityCosts: '95000.00',
            equityIncome: '25000.00',
            equityLoss: '0',
            equityTaxBase: '25000',
            equityTax: '4750',
            dividendGross: '3000.00',
            dividendWHT: '450.00',
            dividendTaxDue: '120',
            cryptoProceeds: '18000.00',
            cryptoCosts: '12000.00',
            cryptoIncome: '6000.00',
            cryptoLoss: '0',
            cryptoTax: '1140',
            totalTax: '6010',
            isCorrection: false,
            kodUrzedu: '0202',
            adresZamieszkania: new PolishAddress(
                miejscowosc: 'Wroclaw',
                nrDomu: '3',
                kodPocztowy: '50-001',
                wojewodztwo: 'dolnoslaskie',
                powiat: 'Wroclaw',
                gmina: 'Wroclaw',
                ulica: 'ul. Swidnicka',
                nrLokalu: '2',
            ),
            dateOfBirth: '1975-04-30',
        );

        $xml = $this->generator->generate($data);

        $this->assertPIT38XmlValidatesAgainstOfficialSchema($xml);
    }

    /**
     * Zero-everything scenario — all required fields at zero. Verifies the generator
     * emits P_26/P_27/P_28/P_31/P_32/P_33 and P_51 even when all values are zero.
     */
    public function testAllZeroValidatesAgainstOfficialXsd(): void
    {
        $data = new PIT38Data(
            taxYear: 2025,
            nip: '5260000005',
            firstName: 'Test',
            lastName: 'Podatnik',
            equityProceeds: '0',
            equityCosts: '0',
            equityIncome: '0',
            equityLoss: '0',
            equityTaxBase: '0',
            equityTax: '0',
            dividendGross: '0',
            dividendWHT: '0',
            dividendTaxDue: '0',
            cryptoProceeds: '0',
            cryptoCosts: '0',
            cryptoIncome: '0',
            cryptoLoss: '0',
            cryptoTax: '0',
            totalTax: '0',
            isCorrection: false,
            kodUrzedu: '0202',
            adresZamieszkania: new PolishAddress(
                miejscowosc: 'Poznan',
                nrDomu: '1',
                kodPocztowy: '60-001',
                wojewodztwo: 'wielkopolskie',
                powiat: 'Poznan',
                gmina: 'Poznan',
            ),
            dateOfBirth: '1980-01-01',
        );

        $xml = $this->generator->generate($data);

        $this->assertPIT38XmlValidatesAgainstOfficialSchema($xml);
    }
}
