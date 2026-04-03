# ADR-011: Polityki obliczania podatków — jak są trzymane i wersjonowane

## Status
ACCEPTED (po review)

## Data
2026-04-03

## Kontekst

Reguły podatkowe to serce systemu. Muszą:
- Być **poprawne** (co do grosza, co do artykułu ustawy)
- Być **wersjonowane** per rok podatkowy (przepisy się zmieniają)
- Być **testowalne** (golden dataset)
- Być **czytelne** dla doradcy podatkowego (nie tylko dla developera)
- Być **rozszerzalne** (nowy typ instrumentu, nowa reguła)
- **NIE być hardcoded** w jednej monolitycznej klasie

### Pytanie kluczowe
Czy polityki to:
1. Kod PHP (strategy pattern, policy classes)?
2. Konfiguracja (YAML/JSON)?
3. Rule engine (business rules framework)?
4. Baza danych (dynamic rules)?

## Decyzja

**Polityki to klasy PHP w Domain layer — Strategy Pattern + wersjonowanie per rok podatkowy.**

### Dlaczego kod, nie konfiguracja?

> **Tomasz [DP]:** "Reguły podatkowe nie są prostymi parametrami. 'Strata z krypto nie łączy się z zyskiem z akcji' — to nie jest `canCombine: false`. To jest logika warunkowa z wyjątkami. Konfiguracja nie da rady."
>
> **Mariusz Gil:** "Jeśli reguła wymaga `if/else` — to jest kod. Konfiguracja jest dla parametrów (stawka 19%, limit 50%). Logika jest w klasach."
>
> **Marek [senior-dev]:** "Klasy PHP = testowalne, type-safe, reviewable w code review. YAML rule engine = untestable black box."

### Architektura polityk

```
packages/tax-rules/
├── src/
│   ├── TaxRuleSet.php                    # interface
│   ├── Year2025TaxRuleSet.php            # implementacja 2025
│   ├── Year2026TaxRuleSet.php            # implementacja 2026
│   │
│   ├── Policy/
│   │   ├── FIFOMatchingPolicy.php        # FIFO — art. 24 ust. 10
│   │   ├── CryptoSeparationPolicy.php    # krypto osobny koszyk — art. 30b ust. 5d
│   │   ├── LossCarryForwardPolicy.php    # straty z lat poprzednich — art. 9 ust. 3
│   │   ├── DividendTaxPolicy.php         # dywidendy zagraniczne — art. 30a
│   │   ├── DerivativeTaxPolicy.php       # instrumenty pochodne — art. 17 ust. 1 pkt 10
│   │   ├── CurrencyConversionPolicy.php  # przeliczenie NBP — art. 11a ust. 1
│   │   └── TaxRoundingPolicy.php         # zaokrąglanie — art. 63 §1 Ordynacji
│   │
│   ├── Rate/
│   │   ├── TaxRate.php                   # value object: stawka + podstawa prawna
│   │   ├── CapitalGainsTaxRate.php       # 19% — art. 30b ust. 1
│   │   └── UPORegistry.php              # stawki WHT per kraj z UPO
│   │
│   └── Classification/
│       ├── InstrumentTaxClassifier.php   # interface
│       ├── TaxCategory.php               # enum: EQUITY, DERIVATIVE, CRYPTO, DIVIDEND
│       └── BasketCombinationMatrix.php   # które koszyki się łączą
│
└── tests/
    ├── FIFOMatchingPolicyTest.php
    ├── CryptoSeparationPolicyTest.php
    ├── LossCarryForwardPolicyTest.php
    └── ...
```

### Struktura polityki (przykład)

```php
// packages/tax-rules/src/Policy/LossCarryForwardPolicy.php

/**
 * Polityka odliczania strat z lat poprzednich.
 *
 * Podstawa prawna: art. 9 ust. 3 ustawy o PIT
 * "O wysokość straty ze źródła przychodów, poniesionej w roku podatkowym,
 *  podatnik może:
 *  1) obniżyć dochód uzyskany z tego źródła w najbliższych kolejno po sobie
 *     następujących pięciu latach podatkowych, z tym że kwota obniżenia
 *     w którymkolwiek z tych lat nie może przekroczyć 50% wysokości tej straty"
 *
 * Wersja: 2019+ (zmiana z 2018: dodano opcję jednorazowego odpisu do 5 mln PLN,
 *          ale to dotyczy działalności gospodarczej, nie kapitałów pieniężnych)
 *
 * @see https://isap.sejm.gov.pl/isap.nsf/DocDetails.xsp?id=WDU19910800350
 */
final readonly class LossCarryForwardPolicy
{
    private const int MAX_CARRY_FORWARD_YEARS = 5;
    private const string MAX_ANNUAL_DEDUCTION_RATE = '0.50';

    /**
     * Oblicza dostępny zakres odpisu straty.
     *
     * UWAGA: metoda NIE rekomenduje kwoty — to byłoby doradztwo podatkowe.
     * Zwraca zakres (0 do maxDeduction). Użytkownik decyduje ile odliczyć.
     *
     * @return LossDeductionRange zakres dostępnego odpisu
     */
    public function availableDeduction(
        PriorYearLoss $loss,
        TaxYear $currentYear,
    ): LossDeductionRange {
        $yearsElapsed = $currentYear->value - $loss->taxYear->value;

        if ($yearsElapsed < 1 || $yearsElapsed > self::MAX_CARRY_FORWARD_YEARS) {
            return LossDeductionRange::zero($loss->taxCategory);
        }

        $maxDeduction = $loss->remainingAmount->multipliedBy(
            BigDecimal::of(self::MAX_ANNUAL_DEDUCTION_RATE),
        )->toScale(2, RoundingMode::DOWN);

        return new LossDeductionRange(
            taxCategory: $loss->taxCategory,
            lossYear: $loss->taxYear,
            originalAmount: $loss->originalAmount,
            remainingAmount: $loss->remainingAmount,
            maxDeductionThisYear: $maxDeduction,
            expiresInYear: TaxYear::of($loss->taxYear->value + self::MAX_CARRY_FORWARD_YEARS),
            yearsRemaining: self::MAX_CARRY_FORWARD_YEARS - $yearsElapsed,
        );
    }
}
```

### Wersjonowanie per rok podatkowy

```php
// packages/tax-rules/src/TaxRuleSet.php
interface TaxRuleSet
{
    public function taxYear(): TaxYear;
    public function capitalGainsTaxRate(): TaxRate;
    public function fifoPolicy(): FIFOMatchingPolicy;
    public function cryptoSeparationPolicy(): CryptoSeparationPolicy;
    public function lossCarryForwardPolicy(): LossCarryForwardPolicy;
    public function dividendTaxPolicy(): DividendTaxPolicy;
    public function currencyConversionPolicy(): CurrencyConversionPolicy;
    public function roundingPolicy(): TaxRoundingPolicy;
    public function basketCombinationMatrix(): BasketCombinationMatrix;
    public function upoRegistry(): UPORegistry;
}

// packages/tax-rules/src/Year2025TaxRuleSet.php
final readonly class Year2025TaxRuleSet implements TaxRuleSet
{
    public function taxYear(): TaxYear
    {
        return TaxYear::of(2025);
    }

    public function capitalGainsTaxRate(): TaxRate
    {
        return TaxRate::of(
            rate: BigDecimal::of('0.19'),
            legalBasis: 'art. 30b ust. 1 ustawy o PIT',
        );
    }

    public function fifoPolicy(): FIFOMatchingPolicy
    {
        return new FIFOMatchingPolicy(); // FIFO nie zmienił się od lat
    }

    public function cryptoSeparationPolicy(): CryptoSeparationPolicy
    {
        return new CryptoSeparationPolicy(
            effectiveSince: TaxYear::of(2019),
            legalBasis: 'art. 30b ust. 5d ustawy o PIT',
        );
    }

    public function lossCarryForwardPolicy(): LossCarryForwardPolicy
    {
        return new LossCarryForwardPolicy();
    }

    // ... etc
}
```

### Rejestracja w Symfony

```php
// src/TaxCalc/Infrastructure/TaxRuleSetRegistry.php
final readonly class TaxRuleSetRegistry
{
    /** @param iterable<TaxRuleSet> $ruleSets */
    public function __construct(
        private iterable $ruleSets,
    ) {}

    public function forYear(TaxYear $year): TaxRuleSet
    {
        foreach ($this->ruleSets as $ruleSet) {
            if ($ruleSet->taxYear()->equals($year)) {
                return $ruleSet;
            }
        }

        throw new UnsupportedTaxYearException($year);
    }
}
```

```yaml
# config/services.yaml
services:
    App\TaxRules\Year2025TaxRuleSet:
        tags: ['app.tax_rule_set']
    App\TaxRules\Year2026TaxRuleSet:
        tags: ['app.tax_rule_set']

    App\TaxCalc\Infrastructure\TaxRuleSetRegistry:
        arguments:
            $ruleSets: !tagged_iterator app.tax_rule_set
```

### Tabela: parametry vs. logika

| Element | Gdzie trzymane | Powód |
|---|---|---|
| Stawka podatku (19%) | `TaxRate` value object w `TaxRuleSet` | Parametr — może się zmienić |
| Limit odpisu straty (50%) | Stała w `LossCarryForwardPolicy` | Parametr — ale rzadko się zmienia |
| Okres odpisu straty (5 lat) | Stała w `LossCarryForwardPolicy` | Parametr |
| Krypto osobny koszyk | `CryptoSeparationPolicy` klasa | Logika — nie jest prostym on/off |
| FIFO matching | `FIFOMatchingPolicy` klasa | Logika — algorytm z edge cases |
| Stawki WHT per kraj | `UPORegistry` (baza danych) | Dane — 90+ krajów, aktualizowane |
| Klasyfikacja instrumentu | `InstrumentTaxClassifier` klasa | Logika — ETN na BTC ≠ krypto |
| Zaokrąglanie podatku | `TaxRoundingPolicy` klasa | Logika — do pełnych złotych w dół |
| Matryca łączenia koszyków | `BasketCombinationMatrix` klasa | Logika/dane — tabela reguł |
| Kurs NBP — który dzień | `CurrencyConversionPolicy` klasa | Logika — dzień-1 roboczy, fallback |

### Każda polityka ma w docblock:

1. **Numer artykułu ustawy** (np. art. 30b ust. 1)
2. **Cytat z ustawy** (dosłowny)
3. **Link do ISAP** (sejm.gov.pl)
4. **Od kiedy obowiązuje** (effective since)
5. **Komentarz doradcy podatkowego** (edge cases z praktyki)

## Review

### Kasia [QA]
> "Każda polityka to osobna klasa = osobny test suite. `LossCarryForwardPolicyTest` testuje: prawidłowy zakres, expired loss, edge case 5-ty rok, max 50%, krypto osobno. Testowalność: 10/10."

### Marek [senior-dev]
> "Strategy pattern + TaxRuleSet per year. Dodanie roku 2027 = nowa klasa `Year2027TaxRuleSet`. Nie ruszamy istniejących. Open/Closed Principle."

### Mec. Wiśniewska [prawnik]
> "Docblock z artykułami i cytatami — doskonale. W razie kontroli lub sporu, możemy wskazać podstawę prawną każdego obliczenia. Wymóg: cytaty muszą być aktualizowane po nowelizacjach."

### Tomasz [DP] (doradca podatkowy)
> "Widzę tu overnight fee w CFD — HS-003 z Event Storming. To jest konfigurowalny parametr, nie twarda reguła. Sugeruję `DerivativeTaxPolicy` z flagą `includeSwapFeeAsCost: bool` — user decyduje, my nie doradzamy. I powinno być ostrzeżenie: 'Traktowanie overnight fee jako kosztu jest kontrowersyjne — skonsultuj z doradcą podatkowym.'"

### Marek [senior-dev] — po review Tomasza
> "Zgoda. `DerivativeTaxPolicy` dostaje parameter `swapFeeStrategy: SwapFeeStrategy` — enum z wartościami `INCLUDE_AS_COST`, `EXCLUDE`, `ASK_USER`. Domyślnie `ASK_USER`."

## Konsekwencje

### Pozytywne
- Polityki czytelne dla doradcy podatkowego (klasa z docblock = dokumentacja)
- Testowalność: każda polityka ma osobny test suite
- Wersjonowanie: nowy rok = nowa klasa, zero zmian w starych
- Rozszerzalność: nowy instrument = nowa polityka, zero zmian w istniejących
- Audit trail: `TaxRuleSet` jest wstrzykiwany — wiemy które reguły użyto

### Negatywne
- Więcej klas (policy per reguła) — ale: lepiej niż monolityczny `TaxCalculator` z 2000 liniami
- Duplikacja między latami (Year2025 vs Year2026 mogą mieć identyczne polityki) — mitigacja: współdzielenie policy instances między latami jeśli się nie zmieniły
- UPO stawki w bazie wymagają migiracji przy zmianie — mitigacja: admin panel lub seed script

## Uczestnicy decyzji

| Osoba | Sign-off |
|---|---|
| Marek [senior-dev] | APPROVED — "Strategy pattern, testowalne, rozszerzalne" |
| Kasia [QA] | APPROVED — "Każda polityka = test suite. Golden dataset weryfikuje end-to-end." |
| Mec. Wiśniewska [prawnik] | APPROVED — "Docblock z artykułami. Aktualizować po nowelizacjach." |
| Tomasz [DP] | APPROVED z uwagą — "SwapFeeStrategy: ASK_USER jako default. Warning dla kontrowersyjnych reguł." |
