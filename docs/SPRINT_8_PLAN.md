# Sprint 8 Plan — "Last Mile: Real Data, Real User"

Sprint goal: User importuje CSV, widzi SWOJE dane na dashboardzie, generuje WAŻNY PIT-38 XML ze SWOIM NIPem.

## Scope (5 US + 1 housekeeping)

| # | Story | Priority | Est | Dependencies |
|---|---|---|---|---|
| US-S8-01 | UserProfile (NIP, imię, nazwisko) | Must | M | none |
| US-S8-02 | Dividend persistence | Must | L | none |
| US-S8-03 | PIT/ZG wiring | Must | M | US-S8-01 + US-S8-02 |
| US-S8-04 | Nav personalizacja | Must | S | US-S8-01 |
| US-S8-05 | Prior Year Loss persistence | Must | M | none |
| US-S8-06 | FALSE DONE korekta BACKLOG | Must | S | none |

## Parallelization

Round 1 (parallel): US-S8-06 + US-S8-01 + US-S8-02 + US-S8-05
Round 2 (after R1): US-S8-03 + US-S8-04

## DoD (Sprint 8+)

1. Kod zaimplementowany (NIE "TODO comment")
2. Testy przechodzą
3. Review PRZED commitem
4. AC spełnione (Given/When/Then)
5. User widzi efekt w przeglądarce
6. BACKLOG zaktualizowany

---

## RETRO (actual, post-sprint)

### Dobrze
- AC przed implementacją — pierwszy sprint z pełnymi Given/When/Then
- VERIFY przed commitem — 0 FALSE DONE
- 3 reviewerzy równolegle, cross-referenced findings
- NIP encryption, UNIQUE constraint, transaction wrapper — naprawione w tym samym sprincie
- Wszystkie stubs eliminated (dividend, prior losses, PDF)

### Źle
- ImportControllerWebTest failures po Tailwind (brak symfony/asset)
- Float cast w PriorYearLossController (convention = BigDecimal)
- DeclarationController 7 dependencies (SRP)

### Action items Sprint 9
- Controllers >5 deps → extract Application Service
- BigDecimal from day one in new code
- Reviewer checks: no broken tests left behind
