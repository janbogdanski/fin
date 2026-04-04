# Regulatory Compliance Map — TaxPilot PIT-38

> Traceability matrix: Polish tax law article → implementing PHP class → verifying test(s).
> Use this document to locate the implementation of any legal requirement during a tax advisor review.

---

## Legend

| Column | Meaning |
|---|---|
| **Article** | Short article reference + one-sentence plain-language description |
| **Requirement** | Specific rule encoded in the system |
| **Class** | Fully-qualified PHP class name (namespace `App\`) |
| **Method** | Entry-point method on that class |
| **Tests** | PHPUnit test class(es) that verify the requirement |
| `[VERIFY]` | Mapping is inferred from code context — must be confirmed by tax advisor |

---

## Art. 30b ustawy o PIT — Zyski kapitałowe (equity, ETF, instrumenty pochodne)

> Dochody z odpłatnego zbycia papierów wartościowych opodatkowane są stawką 19%.
> Podstawa opodatkowania = przychód − koszty uzyskania.
> Akcje, ETF i instrumenty pochodne stanowią jeden wspólny koszyk (Sekcja C PIT-38).

| Article | Requirement | Class | Method | Tests |
|---|---|---|---|---|
| Art. 30b ust. 1 | Stawka podatku 19% od zysku kapitałowego | `TaxCalc\Domain\Model\AnnualTaxCalculation` | `finalize()` | `AnnualTaxCalculationTest`, `AnnualTaxCalculationServiceTest`, `GoldenDataset001TomaszTest` |
| Art. 30b ust. 2 | Dochód = przychód − koszty uzyskania (FIFO) | `TaxCalc\Domain\Model\AnnualTaxCalculation` | `addClosedPositions()` | `GoldenDataset001TomaszTest`, `GoldenDataset002CrossYearFIFOTest`, `GoldenDataset006CrossBrokerFIFOTest` |
| Art. 30b ust. 2 | Wycena w PLN po kursie NBP z dnia poprzedzającego transakcję (art. 11a ust. 1) | `TaxCalc\Domain\Service\CurrencyConverter` | `toPLN()` | `GoldenDataset001TomaszTest`, `GoldenDataset005PLNOnlyTransactionsTest` |
| Art. 30b ust. 2 | Wspólny koszyk: akcje + ETF + instrumenty pochodne | `TaxCalc\Domain\Model\AnnualTaxCalculation` | `addClosedPositions()` | `GoldenDataset010MultiBrokerYearTest` |
| Art. 30b ust. 3 | Strata = koszty − przychód (gdy koszty > przychód) | `TaxCalc\Domain\Model\AnnualTaxCalculation` | `equityGainLoss()` | `GoldenDataset003LossScenarioTest`, `GoldenDataset011LossGainCompensationTest` |
| Art. 30b ust. 5a–5g | Kryptowaluty — oddzielny koszyk (nie kompensuje z equity) | `TaxCalc\Domain\Model\AnnualTaxCalculation` | `addClosedPositions()` z `TaxCategory::CRYPTO` | `AnnualTaxCalculationServiceTest::testPriorYearLossesAreAppliedToCrypto`, `GoldenDataset009ZeroGainScenarioTest` |

---

## Art. 17 ust. 1d ustawy o PIT — Kryptowaluty

> Przychody z odpłatnego zbycia walut wirtualnych kwalifikowane są jako przychody z kapitałów pieniężnych.
> Odrębny koszyk — nie można kompensować strat na kryptowalutach ze stratami na innych papierach wartościowych.

| Article | Requirement | Class | Method | Tests |
|---|---|---|---|---|
| Art. 17 ust. 1d | Kryptowaluta = odrębna kategoria przychodów | `TaxCalc\Domain\ValueObject\TaxCategory` | `CRYPTO` enum case | `LossCarryForwardPolicyTest::testCryptoLossHasSeparateCategory` |
| Art. 17 ust. 1d | Strata krypto nie kompensuje zysku equity i odwrotnie | `TaxCalc\Application\Service\AnnualTaxCalculationService` | `calculate()` — rozdzielone zmienne `$equityUsed` / `$cryptoUsed` | `AnnualTaxCalculationServiceTest::testPriorYearLossesAreAppliedToCrypto` |
| Art. 17 ust. 1d | Odrębne pola XML dla kryptowalut (P_38–P_42) | `Declaration\Domain\Service\PIT38XMLGenerator` | `appendPozycjeSzczegolowe()` | `PIT38XMLGeneratorTest` |
| Art. 17 ust. 1d + art. 11a ust. 1 | Przeliczanie przychodów krypto po kursie NBP | `TaxCalc\Domain\Service\CurrencyConverter` | `toPLN()` | `GoldenDataset005PLNOnlyTransactionsTest` `[VERIFY — brak dedykowanego testu krypto-NBP]` |

---

## Art. 9 ust. 3 ustawy o PIT — Odliczenie straty z lat ubiegłych

> Stratę można odliczyć w ciągu 5 kolejnych lat podatkowych.
> W jednym roku podatkowym odliczenie nie może przekroczyć 50% kwoty straty.
> Kryptowaluty mają odrębny koszyk strat (art. 30b ust. 5a–5g).

| Article | Requirement | Class | Method | Tests |
|---|---|---|---|---|
| Art. 9 ust. 3 | Maksymalne 50% straty rocznie | `TaxCalc\Domain\Policy\LossCarryForwardPolicy` | `calculateRange()` — `MAX_YEARLY_RATIO = '0.50'` | `LossCarryForwardPolicyTest::testCalculatesCorrectMaxDeduction`, `testMaxFiftyPercentOfOriginal` |
| Art. 9 ust. 3 | Strata wygasa po 5 latach | `TaxCalc\Domain\Policy\LossCarryForwardPolicy` | `calculateRange()` — `CARRY_FORWARD_YEARS = 5` | `LossCarryForwardPolicyTest::testReturnsNullForExpiredLoss`, `testFifthYearIsStillValid` |
| Art. 9 ust. 3 | Piąty rok — ostatni dzień odliczenia (yearsRemaining = 0) | `TaxCalc\Domain\Policy\LossCarryForwardPolicy` | `calculateRange()` | `LossCarryForwardPolicyTest::testFifthYearIsStillValid` |
| Art. 9 ust. 3 | Odliczenie ograniczone do pozostałej kwoty (remaining < 50%) | `TaxCalc\Domain\Policy\LossCarryForwardPolicy` | `calculateRange()` — min(50%, remaining) | `LossCarryForwardPolicyTest::testRemainingLessThanFiftyPercentUsesRemaining` |
| Art. 9 ust. 3 | Odliczenie w bieżącym roku nie może przekroczyć bieżącego zysku | `TaxCalc\Application\Service\AnnualTaxCalculationService` | `calculate()` — clamping do `$equityGain` / `$cryptoGain` | `AnnualTaxCalculationServiceTest::testLossDeductionIsClampedToCurrentGain`, `testNoDeductionWhenGainIsNegative` |
| Art. 9 ust. 3 | Krypto straty odliczane wyłącznie od krypto zysku | `TaxCalc\Application\Service\AnnualTaxCalculationService` | `calculate()` | `AnnualTaxCalculationServiceTest::testPriorYearLossesAreAppliedToCrypto` |
| Art. 9 ust. 3 | Pełny scenariusz złożonego odliczenia | `TaxCalc\Domain\Policy\LossCarryForwardPolicy` + `AnnualTaxCalculationService` | `calculateRange()` + `calculate()` | `GoldenDataset007PriorYearLossDeductionTest`, `GoldenDataset011LossGainCompensationTest` |

---

## Art. 63 §1 Ordynacji Podatkowej — Zaokrąglanie podatku

> Podstawy opodatkowania oraz kwoty podatku zaokrągla się do pełnych złotych.
> Końcówki kwot wynoszące mniej niż 50 groszy pomija się, wynoszące 50 i więcej podwyższa się do pełnych złotych.
> Odpowiada `RoundingMode::HALF_UP` na skali 0.

| Article | Requirement | Class | Method | Tests |
|---|---|---|---|---|
| Art. 63 §1 OP | `roundTaxBase()` — zaokrąglenie podstawy opodatkowania | `TaxCalc\Domain\Policy\TaxRoundingPolicy` | `roundTaxBase()` | `TaxRoundingPolicyTest::testRoundTaxBase` |
| Art. 63 §1 OP | `roundTax()` — zaokrąglenie kwoty podatku | `TaxCalc\Domain\Policy\TaxRoundingPolicy` | `roundTax()` | `TaxRoundingPolicyTest::testRoundTax` |
| Art. 63 §1 OP | >= 50 groszy → w górę | `TaxCalc\Domain\Policy\TaxRoundingPolicy` | `roundToFullZloty()` — `RoundingMode::HALF_UP` | `TaxRoundingPolicyTest` (case `'== 50 groszy -> w gore'`) |
| Art. 63 §1 OP | < 50 groszy → w dół | `TaxCalc\Domain\Policy\TaxRoundingPolicy` | `roundToFullZloty()` | `TaxRoundingPolicyTest` (case `'< 50 groszy -> w dol'`) |
| Art. 63 §1 OP | Zaokrąglanie w AnnualTaxCalculation (`finalize()`) | `TaxCalc\Domain\Model\AnnualTaxCalculation` | `finalize()` — wywołuje `TaxRoundingPolicy` | `GoldenDataset008Art63RoundingEdgeCasesTest`, `LossCarryForwardPolicyMutationTest` |

---

## UPO — Podatek od dywidend (odliczenie WHT na podstawie umów o unikaniu podwójnego opodatkowania)

> Art. 30a ust. 1 pkt 4 ustawy o PIT: podatek od dywidend zagranicznych = 19% od przychodu brutto.
> Art. 30a ust. 2: odliczenie podatku zapłaconego za granicą (WHT) do wysokości stawki z UPO.
> Art. 27 ust. 9: metoda proporcjonalnego odliczenia.

| Article | Requirement | Class | Method | Tests |
|---|---|---|---|---|
| Art. 30a ust. 1 pkt 4 | Stawka 19% od dywidendy brutto (PLN) | `TaxCalc\Domain\Service\DividendTaxService` | `calculate()` — `POLISH_TAX_RATE = '0.19'` | `DividendTaxServiceTest::testUsaDividendWith15PercentWht` |
| Art. 30a ust. 2 | Odliczenie WHT cappowane do stawki z UPO | `TaxCalc\Domain\Service\DividendTaxService` | `calculate()` — `BigDecimal::min($actualWHTRate, $upoRate)` | `DividendTaxServiceTest::testWhtIsCappedToUpoRate`, `testWhtExceedsUpoRateDeductionIsCapped` |
| Art. 30a ust. 2 | WHT ≤ UPO rate — odliczenie pełne (bez cappowania) | `TaxCalc\Domain\Service\DividendTaxService` | `calculate()` | `DividendTaxServiceTest::testWhtBelowUpoRateIsNotCapped` |
| Art. 30a ust. 2 | Kraj bez UPO → fallback 19% (brak prawa do odliczenia) | `TaxCalc\Domain\Service\UPORegistry` | `getRate()` — `FALLBACK_DEFAULT_RATE = '0.19'` | `UPORegistryTest::testReturnsDefaultRateWhenNoAgreement`, `DividendTaxServiceTest::testCountryWithoutUpoReturnsDefaultRate` |
| Art. 11a ust. 1 | Przeliczenie dywidendy zagranicznej na PLN kursem NBP | `TaxCalc\Domain\Service\DividendTaxService` | `calculate()` — wywołuje `CurrencyConverter::toPLN()` | `DividendTaxServiceTest::testUsaDividendWith15PercentWht` |
| UPO (wiele krajów) | Rejestr stawek WHT: US/GB/DE/IE/NL/CH/CA/JP/AU/LU/FR/SE/NO/DK/FI | `TaxCalc\Domain\Service\UPORegistry` | `getRate()`, `hasAgreement()` | `UPORegistryTest::testReturnsCorrectRateForKnownCountry` |
| UPO (wiele krajów) | Stawki konfigurowalne przez `config/upo_rates.yaml` (DI) | `TaxCalc\Domain\Service\UPORegistry` | `__construct()` | `UPORegistryTest::testAcceptsCustomRatesViaConstructor` |
| Art. 30a ust. 1 pkt 4 | Pełny scenariusz: dywidenda zagraniczna z WHT i cappowaniem UPO | `DividendTaxService` + `UPORegistry` | `calculate()` + `getRate()` | `GoldenDataset004ForeignDividendsUPOCapTest` |

---

## Art. 45 ust. 1a pkt 1 ustawy o PIT — Formularz PIT-38

> Podatnicy uzyskujący dochody, o których mowa w art. 30b, składają zeznanie podatkowe PIT-38
> w terminie do 30 kwietnia roku następującego po roku podatkowym.

| Article | Requirement | Class | Method | Tests |
|---|---|---|---|---|
| Art. 45 ust. 1a pkt 1 | Generowanie XML PIT-38 (wersja 17, namespace e-Deklaracje MF) | `Declaration\Domain\Service\PIT38XMLGenerator` | `generate()` | `PIT38XMLGeneratorTest::testGeneratesValidXML` |
| Art. 45 ust. 1a pkt 1 | Sekcja C (P_22–P_27): equity przychód, koszty, dochód, strata, podstawa, podatek | `Declaration\Domain\Service\PIT38XMLGenerator` | `appendPozycjeSzczegolowe()` | `PIT38XMLGeneratorTest::testContainsEquitySection`, `GoldenXMLSnapshotTest` |
| Art. 45 ust. 1a pkt 1 | Sekcja D (P_28–P_30): dywidendy zagraniczne | `Declaration\Domain\Service\PIT38XMLGenerator` | `appendPozycjeSzczegolowe()` | `PIT38XMLGeneratorTest::testContainsDividendSection` |
| Art. 45 ust. 1a pkt 1 | Sekcja krypto (P_38–P_42): kryptowaluty | `Declaration\Domain\Service\PIT38XMLGenerator` | `appendPozycjeSzczegolowe()` | `PIT38XMLGeneratorTest` |
| Art. 45 ust. 1a pkt 1 | P_51: łączny podatek należny | `Declaration\Domain\Service\PIT38XMLGenerator` | `appendPozycjeSzczegolowe()` | `PIT38XMLGeneratorTest` |
| Art. 45 ust. 1a pkt 1 | Cel złożenia: 1 = złożenie, 2 = korekta (`isCorrection`) | `Declaration\Domain\DTO\PIT38Data` | `isCorrection` field + `PIT38XMLGenerator::appendNaglowek()` | `PIT38XMLGeneratorTest` `[VERIFY — brak dedykowanego testu korekty]` |
| Art. 45 ust. 1a pkt 1 | Walidacja NIP (10 cyfr, algorytm sumy kontrolnej) | `Declaration\Domain\DTO\PIT38Data` | `validateNip()` | `PIT38DataTest` |
| Art. 45 ust. 1a pkt 1 | XML niepoprawny bez kompletnych danych osobowych | `Declaration\Domain\Service\PIT38XMLGenerator` | `generate()` — guard `hasCompletePersonalData()` | `PIT38XMLGeneratorTest` |

---

## Zestawienie plików — szybki dostęp

| Klasa | Ścieżka |
|---|---|
| `AnnualTaxCalculation` | `src/TaxCalc/Domain/Model/AnnualTaxCalculation.php` |
| `AnnualTaxCalculationService` | `src/TaxCalc/Application/Service/AnnualTaxCalculationService.php` |
| `TaxRoundingPolicy` | `src/TaxCalc/Domain/Policy/TaxRoundingPolicy.php` |
| `LossCarryForwardPolicy` | `src/TaxCalc/Domain/Policy/LossCarryForwardPolicy.php` |
| `DividendTaxService` | `src/TaxCalc/Domain/Service/DividendTaxService.php` |
| `UPORegistry` | `src/TaxCalc/Domain/Service/UPORegistry.php` |
| `CurrencyConverter` | `src/TaxCalc/Domain/Service/CurrencyConverter.php` |
| `PIT38XMLGenerator` | `src/Declaration/Domain/Service/PIT38XMLGenerator.php` |
| `PIT38Data` | `src/Declaration/Domain/DTO/PIT38Data.php` |

---

*Ostatnia aktualizacja: 2026-04-04. Wersja formularza PIT-38: 17 (rok podatkowy 2025).*
