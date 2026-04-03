# ADR-006: brick/math BigDecimal — nigdy float dla pieniędzy

## Status
ACCEPTED

## Data
2026-04-03

## Kontekst

Aplikacja oblicza podatki. Wynik musi być poprawny **co do grosza**. Różnica 0.01 PLN to bug.

PHP `float` to IEEE 754 double-precision:
```php
var_dump(0.1 + 0.2);        // float(0.30000000000000004)
var_dump(0.1 + 0.2 == 0.3); // bool(false)
```

Przy FIFO matching na 5000 transakcjach — rounding errors się kumulują. Wynik może się różnić o kilka złotych od prawidłowego.

### Rozważane:
1. **PHP float + round()** — proste, ale tracisz precyzję w trakcie obliczeń
2. **bcmath (string-based)** — wbudowane w PHP, ale API jest string-only, brak Value Object
3. **brick/math** — BigDecimal z immutable API, operator overloading, rounding modes
4. **moneyphp/money** — integer-based (groszówki), ale nie obsługuje cross-currency i partial quantities

## Decyzja

**brick/math BigDecimal** jako jedyna reprezentacja kwot i ilości.

### Reguły (NIEKOMPROMISOWE):

1. **NIGDY `float` ani `int` dla kwot** — nawet w testach, nawet "tymczasowo"
2. **BigDecimal w Domain** — Money value object opakowuje BigDecimal + CurrencyCode
3. **Skala 2 dla kwot w PLN** — `toScale(2, RoundingMode::HALF_UP)` na końcu obliczeń
4. **Skala 4 dla kursów NBP** — NBP publikuje z 4 miejscami po przecinku
5. **Skala 8+ dla kryptowalut** — BTC ma 8 decimal places (satoshi)
6. **Rounding dopiero na końcu** — intermediate calculations bez zaokrąglania (Money::of() NIE zaokrągla, Money::rounded() zaokrągla)
7. **Zaokrąglanie podatkowe: MATEMATYCZNE** — art. 63 §1 Ordynacji podatkowej: >= 50 groszy w górę, < 50 groszy w dół. Dotyczy PODSTAWY OPODATKOWANIA i PODATKU. NIE "zawsze w dół"!

### Implementacja

```php
// Money — value object
final readonly class Money
{
    private function __construct(
        private BigDecimal $amount,
        private CurrencyCode $currency,
    ) {}

    public static function of(string|BigDecimal $amount, CurrencyCode $currency): self
    {
        return new self(
            BigDecimal::of($amount)->toScale(2, RoundingMode::HALF_UP),
            $currency,
        );
    }

    public function toPLN(NBPRate $rate): self
    {
        if ($this->currency->equals(CurrencyCode::PLN)) {
            return $this;
        }
        return new self(
            $this->amount->multipliedBy($rate->rate)->toScale(2, RoundingMode::HALF_UP),
            CurrencyCode::PLN,
        );
    }

    // ... add, subtract, multiply — all return new Money (immutable)
}
```

### PHPStan rule
Custom PHPStan rule: jeśli `float` pojawia się w namespace `Domain\` przy kwotach — error.

### Database
PostgreSQL `NUMERIC(19,4)` dla kwot — nie `REAL`, nie `DOUBLE PRECISION`.

## Konsekwencje

### Pozytywne
- Wyniki poprawne co do grosza — zero rounding drift
- BigDecimal jest immutable — bezpieczny w wielowątkowym/async
- Explicit rounding mode — developoer MUSI zdecydować jak zaokrąglić (brak domyślnego cichego zaokrąglenia)
- Tomasz (doradca podatkowy) może zweryfikować obliczenia do grosza

### Negatywne
- Bardziej verbose niż `float`: `BigDecimal::of('170.00')` zamiast `170.00`
- Wolniejsze niż float (~10x) — ale: FIFO 10k transakcji < 2s, akceptowalne
- Trzeba serializować/deserializować BigDecimal (Doctrine custom type)

## Uczestnicy decyzji

| Osoba | Stanowisko |
|---|---|
| Marek [senior-dev] | "decimal.js / brick/math WSZĘDZIE. Nawet w testach. To jest niekompromisowe." |
| Kasia [QA] | "Grosze mają znaczenie. Różnica 0.01 PLN to bug, nie rounding error." |
| Tomasz [DP] | "US kontroluje do grosza. Jeśli wasz system daje 1 926.98 a powinno być 1 926.97 — to jest błąd." |
