# ADR-017: Multi-Year FIFO — agregat bez ograniczenia roku podatkowego

## Status
ACCEPTED

## Data
2026-04-03

## Kontekst

Review QA Lead (B-03): TaxPositionLedger był scoped per (UserId, ISIN, TaxYear). Ale FIFO jest ciągłe cross-year — akcja kupiona w 2023 i sprzedana w 2026 wymaga open position z 2023 w FIFO queue.

> **Tomasz [DP]:** "80% moich klientów to buy-and-hold. Kupują w jednym roku, sprzedają za 3-5 lat. Bez cross-year FIFO system jest bezużyteczny."

## Decyzja

**TaxPositionLedger scoped per (UserId, ISIN) — BEZ TaxYear.**

### Aggregate identity

```php
// STARE (BŁĘDNE):
// TaxPositionLedger per (UserId, ISIN, TaxYear)

// NOWE (POPRAWNE):
// TaxPositionLedger per (UserId, ISIN)
final class TaxPositionLedger
{
    private UserId $userId;
    private ISIN $isin;
    private TaxCategory $taxCategory;
    // BEZ TaxYear — FIFO queue żyje dopóki są open positions

    /** @var list<OpenPosition> — sorted by date ASC, cross-year */
    private array $openPositions = [];
}
```

### Jak to działa

1. User kupuje 100 AAPL w 2023 → `openPositions` zawiera 1 pozycję
2. User kupuje 50 AAPL w 2024 → `openPositions` zawiera 2 pozycje
3. User sprzedaje 80 AAPL w 2026 → FIFO: 80 z 2023 buy → ClosedPosition z cross-year dates
4. User ma 20 AAPL z 2023 + 50 z 2024 = 70 open

### ClosedPositions — osobna tabela, per TaxYear

ClosedPositions (wynik FIFO matching) SĄ per TaxYear — bo trafiają do konkretnego PIT-38.

```sql
-- closed_positions table
CREATE TABLE closed_positions (
    id UUID PRIMARY KEY,
    ledger_id UUID REFERENCES tax_position_ledgers(id),
    sell_tax_year INT NOT NULL,  -- rok sprzedaży = rok PIT-38
    buy_date DATE NOT NULL,
    sell_date DATE NOT NULL,
    -- ... kwoty, kursy NBP, etc.
);

CREATE INDEX idx_closed_by_year ON closed_positions(user_id, sell_tax_year);
```

Query "pokaż obliczenie za 2026" → filtruje closed_positions WHERE sell_tax_year = 2026.

### Onboarding — Opening Balance

Nowy user rozlicza 2026, ale ma pozycje kupione w 2023-2025.

Dwie ścieżki:

**A) Import historyczny** (preferowane):
User wgrywa CSV z historią od początku. System buduje FIFO queue od zera.

**B) Manual opening balance:**
User podaje: "Mam 100 AAPL kupione 15.03.2023 po $170". System tworzy OpenPosition z tymi danymi.

UI musi jasno informować: "Aby FIFO było poprawne, potrzebujemy historii WSZYSTKICH zakupów instrumentu — nie tylko z bieżącego roku."

### Memory footprint

OpenPositions per ISIN per user: rzadko > 50. Typowy buy-and-hold: 1-5 transzy per instrument. Nawet active trader: 20-30 open lots per instrument. To jest small aggregate.

Wyjątek: crypto boty (1000+ open lots per symbol). Mitigacja: lazy loading, pagination, lub stream processing.

## Konsekwencje

### Pozytywne
- FIFO poprawne cross-year — jedyna poprawna interpretacja art. 24 ust. 10
- Aggregate jest mały (tylko open positions — zamknięte nie ładowane)
- Naturalny model — ledger żyje dopóki user ma pozycję w instrumencie

### Negatywne
- Onboarding wymaga historii — UX challenge (mitigacja: wizard z wyjaśnieniem)
- Aggregate nie ma daty "końca" — żyje dopóki open positions > 0
- Migracja z potencjalnego starego per-year modelu (ale nie mamy kodu — OK)

## Uczestnicy decyzji

| Osoba | Stanowisko |
|---|---|
| Tomasz [DP] | "FIFO jest ciągłe. To nie jest decyzja architektoniczna — to jest prawo." |
| Marek [senior-dev] | "Aggregate per (UserId, ISIN). Small, focused. ClosedPositions append-only, osobna tabela." |
| Kasia [QA] | "Golden dataset #11 (cross-year) jest teraz krytyczny." |
