# End-to-End Wiring: 4 Technique Analysis

Data: 2026-04-02
Techniki: User Story Mapping, JTBD, Impact Mapping, Example Mapping

## Root Cause

Domena jest kompletna (461 testów). Warstwa prezentacji jest kompletna (templates).
Brakuje "last mile wiring": persystencja importu + podłączenie kontrolerów do query handlers.

## User Story Map (Jeff Patton)

| Krok usera → | Import CSV | Podgląd | Oblicz podatek | Dashboard | PIT-38 | Eksport |
|---|---|---|---|---|---|---|
| **MUST** | Wybierz brokera ✅ | Tabela tx ✅ | FIFO auto ✅(testy) | Podsumowanie ❌ | Preview ❌ | XML ❌ |
| **MUST** | Wrzuć plik ✅ | Potwierdź+zapisz ❌ | Kursy NBP ✅(testy) | Zysk/strata ❌ | Dane z profilu ❌ | Value gate ❌(mock) |
| **SHOULD** | Multi-file ❌ | Edytuj tx ❌ | Prior losses ✅(testy) | Per ISIN ❌ | PIT/ZG ❌ | PDF audit ❌ |

✅ = DONE, ❌ = NOT STARTED, przerwanie w "Potwierdź+zapisz"

## JTBD — 7 Jobs

1. **Złożyć PIT-38 w 15 min** → BROKEN (dashboard = zera)
2. **Cross-broker FIFO auto** → domain DONE, wiring BROKEN
3. **Zrozumieć skąd kwota** → NOT STARTED (drill-down)
4. **Pobrać XML do e-Deklaracji** → PARTIAL (generator OK, dane = demo)
5. **Odliczyć straty** → domain DONE, UI NONE
6. **Wiedzieć ile kosztuje** → PARTIAL (gate = hardcoded)
7. **Rozliczyć dywidendy** → domain DONE, wiring BROKEN

## Impact Map — 3 Actors

Inwestor pasywny → nie musi rozumieć FIFO → DELIVERABLE: persist + auto-calc + dashboard wiring
Aktywny trader → multi-broker merge → DELIVERABLE: cross-broker persist + dedup DB
Biuro rachunkowe → oszczędza 4-6h/klient → DELIVERABLE: multi-user + batch export (v2)

## Example Mapping — 3 Reguły

### FIFO cross-broker
✅ IBKR BUY + Degiro SELL → match cross-broker
✅ Partial sell across lots
❌ Różne ISIN (ADR vs orig) → osobne kolejki
? Re-kalkulacja po dodatkowym imporcie: auto czy manual?

### Kurs NBP
✅ Środa → kurs z wtorku
✅ Poniedziałek → kurs z piątku
✅ Długi weekend (Boże Ciało) → cofaj do środy
❌ 7 dni bez kursu → ExchangeRateNotFoundException
? "Dzień roboczy" = NBP czy Kodeks Pracy?

### Value gate
✅ 1 broker, 25 pozycji → FREE
✅ 2 brokery → REQUIRES_STANDARD → Stripe
❌ Hardcoded brokerCount=1 w kontrolerze → gate nie działa
? Preview PIT-38: pełny czy zamazany dla unpaid?

## Brakujące elementy (priorytet)

| Element | Blokuje | Must/Should |
|---|---|---|
| Transaction persistence | WSZYSTKO | Must |
| Doctrine ClosedPositionQueryPort | Dashboard, PIT | Must |
| Doctrine DividendResultQueryPort | Dashboard, PIT | Must |
| DashboardController wiring | Dashboard | Must |
| DeclarationController wiring | PIT-38 | Must |
| Value gate real counts | Billing | Must |
| Import confirm flow | Persistence | Must |
| Auto-calc after import | UX | Should |
